// ISMS.exe - the whole offline demo in one file.
//
// PHP and the app ride inside this exe as an embedded zip. The first launch
// unpacks them to %LOCALAPPDATA%\ISMS\<build>, creates the database with
// sample data, and from then on it just starts PHP's built-in server and opens
// the browser. Built by build.sh with the csc.exe that ships with Windows'
// .NET Framework 4, which every Windows 10/11 has - nothing to install.
//
// PHP runs as a child sharing this console: closing the window stops both.
using System;
using System.Diagnostics;
using System.IO;
using System.IO.Compression;
using System.Net;
using System.Net.Sockets;
using System.Reflection;
using System.Threading;

class Launcher
{
    static readonly string Home = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "ISMS");
    static readonly string Root = Path.Combine(Home, BuildInfo.Id);
    static readonly string App = Path.Combine(Root, "app");
    static readonly string Php = Path.Combine(Root, "php", "php.exe");
    static readonly string PhpArgs = "-c \"" + Path.Combine(Root, "php", "php.ini") + "\" -d extension_dir=\"" + Path.Combine(Root, "php", "ext") + "\"";

    static int Main()
    {
        Console.Title = "Inventory, Sales and Management System";

        if (!Directory.Exists(Root))
            Unpack();

        if (!File.Exists(Path.Combine(App, "database", "database.sqlite")) && !CreateDatabase())
            return Fail("Setting up the database failed - see the messages above.");

        LinkStorage();

        int port = FreePort();
        // Laravel's router script takes the document root from the working directory.
        var server = Start("-S 127.0.0.1:" + port + " ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php", Path.Combine(App, "public"));
        if (!WaitForPort(port))
            return Fail("The server did not start.");

        string url = "http://127.0.0.1:" + port;
        Console.WriteLine();
        Console.WriteLine("  Running at " + url + " - opening your browser.");
        Console.WriteLine();
        Console.WriteLine("  Sign in with password: password");
        Console.WriteLine("    owner@example.test       Owner - financial dashboard, everything");
        Console.WriteLine("    sam@example.test         Head Chef");
        Console.WriteLine("    chef1@example.test       Junior Chef (also chef2, chef3)");
        Console.WriteLine("    parttimer@example.test   Part timer");
        Console.WriteLine("    admin@example.test       Admin / support");
        Console.WriteLine();
        Console.WriteLine("  Close this window to stop. To start again with fresh sample data,");
        Console.WriteLine("  delete the folder " + Home);
        Console.WriteLine();
        Process.Start(url);

        server.WaitForExit();
        return 0;
    }

    // Into a scratch folder first, renamed only when complete, so a launch that
    // is closed half-way unpacks again rather than running a partial copy.
    static void Unpack()
    {
        Console.WriteLine("First launch: unpacking (this takes a minute, and only happens once)...");
        string partial = Root + ".partial";
        if (Directory.Exists(partial))
            Directory.Delete(partial, true);

        using (var payload = Assembly.GetExecutingAssembly().GetManifestResourceStream("payload.zip"))
        using (var zip = new ZipArchive(payload))
            zip.ExtractToDirectory(partial);
        Directory.Move(partial, Root);

        RemoveOlderBuilds();
    }

    // Each build unpacks beside the last; only the current one is ever used.
    static void RemoveOlderBuilds()
    {
        foreach (string dir in Directory.GetDirectories(Home))
        {
            if (dir == Root)
                continue;
            try { Directory.Delete(dir, true); }
            catch (IOException) { }
            catch (UnauthorizedAccessException) { }
        }
    }

    // Seeded on first launch, not at build time, so the sample weeks always end today.
    static bool CreateDatabase()
    {
        Console.WriteLine("Creating the database with sample data...");
        string db = Path.Combine(App, "database", "database.sqlite");
        File.WriteAllBytes(db, new byte[0]);

        // Quiet unless it fails: the migrations print notes about the live
        // database they were written for, which only alarm someone trying a demo.
        var info = new ProcessStartInfo(Php, PhpArgs + " artisan migrate --force --seed --seeder=SampleDataSeeder --no-interaction --no-ansi")
            { WorkingDirectory = App, UseShellExecute = false, RedirectStandardOutput = true, RedirectStandardError = true };
        var setup = Process.Start(info);
        var stderr = setup.StandardError.ReadToEndAsync();
        string output = setup.StandardOutput.ReadToEnd() + stderr.Result;
        setup.WaitForExit();
        if (setup.ExitCode == 0)
            return true;

        Console.WriteLine(output);
        File.Delete(db); // so the next launch tries again rather than opening a half-built database
        return false;
    }

    // Uploaded photos are served from public/storage. A junction, because a
    // symlink needs admin rights and a junction does not.
    static void LinkStorage()
    {
        string link = Path.Combine(App, "public", "storage");
        if (Directory.Exists(link))
            return;
        var p = Process.Start(new ProcessStartInfo("cmd.exe", "/c mklink /J \"" + link + "\" \"" + Path.Combine(App, "storage", "app", "public") + "\" >nul")
            { UseShellExecute = false, CreateNoWindow = true });
        p.WaitForExit();
    }

    static Process Start(string args, string workingDirectory)
    {
        return Process.Start(new ProcessStartInfo(Php, PhpArgs + " " + args) { WorkingDirectory = workingDirectory, UseShellExecute = false });
    }

    static int FreePort()
    {
        for (int port = 8000; port < 8100; port++)
        {
            try { var l = new TcpListener(IPAddress.Loopback, port); l.Start(); l.Stop(); return port; }
            catch (SocketException) { }
        }
        return 8000;
    }

    static bool WaitForPort(int port)
    {
        for (int i = 0; i < 60; i++)
        {
            try { using (new TcpClient("127.0.0.1", port)) return true; }
            catch (SocketException) { Thread.Sleep(250); }
        }
        return false;
    }

    static int Fail(string message)
    {
        Console.WriteLine();
        Console.WriteLine("  " + message);
        Console.WriteLine("  Press Enter to close.");
        Console.ReadLine();
        return 1;
    }
}
