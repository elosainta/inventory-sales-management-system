<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Support Report</title>
</head>
<body style="margin:0; padding:0; background:#f5f0eb; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f0eb; padding:40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:8px; overflow:hidden; border:1px solid #e5ddd5;">
                    {{-- Header --}}
                    <tr>
                        <td style="background:hsl(24,10%,12%); padding:24px 32px;">
                            <span style="font-family:Georgia,serif; font-size:24px; color:#f5f0eb; font-weight:600;">ISMS</span>
                            <span style="font-size:13px; color:rgba(245,240,235,0.6); margin-left:12px;">Kitchen OS · Support Report</span>
                        </td>
                    </tr>
                    {{-- Body --}}
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 24px; font-size:15px; color:#3a3330;">
                                A user has submitted a support report. Details below.
                            </p>

                            {{-- Sender info --}}
                            <table width="100%" cellpadding="0" cellspacing="0" style="background:#faf7f4; border:1px solid #e5ddd5; border-radius:6px; margin-bottom:24px;">
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.06em; color:#8a7060; margin-bottom:10px; font-weight:600;">Submitted by</div>
                                        <div style="font-size:15px; font-weight:600; color:#2a2320; margin-bottom:4px;">{{ $senderName }}</div>
                                        <div style="font-size:13px; color:#6a5a50; margin-bottom:2px;">{{ $senderEmail }}</div>
                                        <div style="font-size:12px; color:#9a8878; text-transform:capitalize;">{{ $senderRole }}</div>
                                    </td>
                                </tr>
                            </table>

                            {{-- Description --}}
                            <div style="margin-bottom:8px; font-size:12px; text-transform:uppercase; letter-spacing:0.06em; color:#8a7060; font-weight:600;">Problem Description</div>
                            <div style="background:#faf7f4; border:1px solid #e5ddd5; border-radius:6px; padding:16px 20px; font-size:14px; color:#2a2320; line-height:1.7; white-space:pre-wrap; margin-bottom:24px;">{{ $description }}</div>

                            {{-- Attachment note --}}
                            @if($mediaFilename)
                                <div style="font-size:13px; color:#6a5a50; background:#fef9c3; border:1px solid #fde68a; border-radius:6px; padding:12px 16px;">
                                    <strong>Attachment:</strong> {{ $mediaFilename }}
                                </div>
                            @else
                                <div style="font-size:13px; color:#9a8878; font-style:italic;">
                                    No media attachment was included with this report.
                                </div>
                            @endif
                        </td>
                    </tr>
                    {{-- Footer --}}
                    <tr>
                        <td style="background:#faf7f4; border-top:1px solid #e5ddd5; padding:16px 32px;">
                            <p style="margin:0; font-size:12px; color:#9a8878;">
                                Sent automatically from Inventory, Sales and Management System &mdash; {{ now()->format('d M Y, g:i A') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
