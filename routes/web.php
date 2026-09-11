<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\InvoiceScanController;
use App\Http\Controllers\PurchaseController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WastageEntryController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\FloatIssuanceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\PrepChecklistController;
use App\Http\Controllers\SpecialEventController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LoginHistoryController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\MaintenanceModeController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\MarketPurchaseController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\LeaveApplicationController;
use App\Http\Controllers\DailyReportController;
use App\Http\Controllers\AboutController;
use App\Http\Controllers\RndEntryController;
use App\Http\Controllers\StaffMealController;
use App\Http\Controllers\InventoryTallyController;
use App\Http\Controllers\StockTakeController;
use App\Http\Controllers\StockTakeItemController;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route(auth()->user()->homeRoute())
        : redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('auth')
    ->name('dashboard');

Route::post('/maintenance/toggle', [MaintenanceModeController::class, 'toggle'])
    ->middleware('auth')
    ->name('maintenance.toggle');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::patch('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');

    Route::get('/inventory', [InventoryItemController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/export/pdf', [InventoryItemController::class, 'exportPdf'])->name('inventory.export-pdf');
    Route::post('/inventory', [InventoryItemController::class, 'store'])->name('inventory.store');
    Route::patch('/inventory/{inventoryItem}', [InventoryItemController::class, 'update'])->name('inventory.update');
    Route::delete('/inventory/{inventoryItem}', [InventoryItemController::class, 'destroy'])->name('inventory.destroy');

    Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store');
    Route::patch('/purchases/{purchase}', [PurchaseController::class, 'update'])->name('purchases.update');
    Route::patch('/purchases/{purchase}/toggle-status', [PurchaseController::class, 'toggleStatus'])->name('purchases.toggle-status');
    Route::delete('/purchases/{purchase}', [PurchaseController::class, 'destroy'])->name('purchases.destroy');
    Route::get('/purchases/{purchase}/receipt', [PurchaseController::class, 'receipt'])->name('purchases.receipt');

    // Invoice scan (BETA) - upload here, bill in Bukku, result back here.
    // store is throttled because each scan is a paid API call.
    Route::get('/invoice-scan', [InvoiceScanController::class, 'index'])->name('invoice-scan.index');
    Route::post('/invoice-scan', [InvoiceScanController::class, 'store'])->name('invoice-scan.store')->middleware('throttle:10,1');
    Route::get('/invoice-scan/{invoiceScan}', [InvoiceScanController::class, 'show'])->name('invoice-scan.show');
    Route::post('/invoice-scan/{invoiceScan}/push', [InvoiceScanController::class, 'push'])->name('invoice-scan.push')->middleware('throttle:20,1');
    Route::get('/invoice-scan/{invoiceScan}/photo', [InvoiceScanController::class, 'photo'])->name('invoice-scan.photo');
    Route::delete('/invoice-scan/{invoiceScan}', [InvoiceScanController::class, 'destroy'])->name('invoice-scan.destroy');

    // R&D purchases. approve/reject are their own routes on their own gate —
    // everything above them is clerical, the decision is the Owner's.
    Route::get('/rnd', [RndEntryController::class, 'index'])->name('rnd.index');
    Route::post('/rnd', [RndEntryController::class, 'store'])->name('rnd.store');
    Route::patch('/rnd/{rndEntry}', [RndEntryController::class, 'update'])->name('rnd.update');
    Route::delete('/rnd/{rndEntry}', [RndEntryController::class, 'destroy'])->name('rnd.destroy');
    Route::patch('/rnd/{rndEntry}/approve', [RndEntryController::class, 'approve'])->name('rnd.approve');
    Route::patch('/rnd/{rndEntry}/reject', [RndEntryController::class, 'reject'])->name('rnd.reject');
    // Writing an approved trial up as a dish. On manage-recipes, not decide-rnd
    // — the approval is the Owner's, the recipe is recipe work.
    Route::post('/rnd/{rndEntry}/recipe', [RndEntryController::class, 'createRecipe'])->name('rnd.recipe');

    // Staff meals. A report — no approve/reject, and nothing here moves stock.
    Route::get('/staff-meals', [StaffMealController::class, 'index'])->name('staff-meals.index');
    Route::post('/staff-meals', [StaffMealController::class, 'store'])->name('staff-meals.store');
    Route::patch('/staff-meals/{staffMeal}', [StaffMealController::class, 'update'])->name('staff-meals.update');
    Route::delete('/staff-meals/{staffMeal}', [StaffMealController::class, 'destroy'])->name('staff-meals.destroy');

    Route::get('/wastage', [WastageEntryController::class, 'index'])->name('wastage.index');
    Route::post('/wastage', [WastageEntryController::class, 'store'])->name('wastage.store');
    Route::patch('/wastage/{wastageEntry}', [WastageEntryController::class, 'update'])->name('wastage.update');
    Route::delete('/wastage/{wastageEntry}', [WastageEntryController::class, 'destroy'])->name('wastage.destroy');

    Route::get('/recipes', [RecipeController::class, 'index'])->name('recipes.index');
    Route::get('/recipes/export/pdf', [RecipeController::class, 'exportPdf'])->name('recipes.export-pdf');
    Route::get('/recipes/{recipe}', [RecipeController::class, 'show'])->name('recipes.show');
    Route::post('/recipes', [RecipeController::class, 'store'])->name('recipes.store');
    Route::put('/recipes/{recipe}', [RecipeController::class, 'update'])->name('recipes.update');
    Route::delete('/recipes/{recipe}', [RecipeController::class, 'destroy'])->name('recipes.destroy');
    Route::post('/recipes/{id}/restore', [RecipeController::class, 'restore'])->name('recipes.restore');

    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
    Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
    Route::post('/sales/sheet', [SaleController::class, 'storeSheet'])->name('sales.sheet');
    Route::patch('/sales/{sale}', [SaleController::class, 'update'])->name('sales.update');
    Route::delete('/sales/{sale}', [SaleController::class, 'destroy'])->name('sales.destroy');
    Route::post('/sales/{sale}/restore', [SaleController::class, 'restore'])->name('sales.restore');
    Route::get('/sales/attachments/{saleAttachment}', [SaleController::class, 'attachment'])->name('sales.attachment');

    Route::get('/float', [FloatIssuanceController::class, 'index'])->name('float.index');
    Route::post('/float', [FloatIssuanceController::class, 'store'])->name('float.store');
    Route::delete('/float/{floatIssuance}', [FloatIssuanceController::class, 'destroy'])->name('float.destroy');

    Route::get('/search', [SearchController::class, 'index'])->name('search.index');

    Route::get('/audit-log', [AuditController::class, 'index'])->name('audits.index');
    Route::get('/login-history', [LoginHistoryController::class, 'index'])->name('login-history.index');

    Route::get('/prep', [PrepChecklistController::class, 'index'])->name('prep.index');
    Route::get('/prep/overview', [PrepChecklistController::class, 'overview'])->name('prep.overview');
    Route::post('/prep/task-check', [PrepChecklistController::class, 'storeTaskCheck'])->name('prep.task-check');
    Route::get('/prep/task-check/{sectionCheck}/photo', [PrepChecklistController::class, 'taskCheckPhoto'])->name('prep.task-check.photo');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::patch('/users/{user}/password', [UserController::class, 'updatePassword'])->name('users.update-password');
    Route::patch('/users/{user}/language', [UserController::class, 'updateLanguage'])->name('users.update-language');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    Route::post('/invitations', [InvitationController::class, 'store'])->name('invitations.store');

    Route::get('/sections', [SectionController::class, 'index'])->name('sections.index');
    Route::post('/sections', [SectionController::class, 'store'])->name('sections.store');
    Route::patch('/sections/{section}', [SectionController::class, 'update'])->name('sections.update');
    Route::delete('/sections/{section}', [SectionController::class, 'destroy'])->name('sections.destroy');
    Route::post('/sections/{section}/tasks', [SectionController::class, 'storeTask'])->name('sections.tasks.store');
    Route::patch('/sections/{section}/tasks/{task}', [SectionController::class, 'updateTask'])->name('sections.tasks.update');
    Route::delete('/sections/{section}/tasks/{task}', [SectionController::class, 'destroyTask'])->name('sections.tasks.destroy');

    Route::post('/notifications/dismiss-low-stock', function () {
        \Illuminate\Support\Facades\Gate::authorize('dismiss-low-stock');
        auth()->user()->unreadNotifications
            ->where('type', \App\Notifications\LowStockAlert::class)
            ->each->markAsRead();
        return back();
    })->name('notifications.dismiss-low-stock');

    Route::get('/events', [SpecialEventController::class, 'index'])->name('events.index');
    Route::post('/events', [SpecialEventController::class, 'store'])->name('events.store');
    Route::delete('/events/{event}', [SpecialEventController::class, 'destroy'])->name('events.destroy');
    Route::get('/events/export/pdf', [SpecialEventController::class, 'exportPdf'])->name('events.export-pdf');

    Route::get('/purchases/export/pdf', [PurchaseController::class, 'exportPdf'])->name('purchases.export-pdf');
    Route::get('/sales/export/pdf', [SaleController::class, 'exportPdf'])->name('sales.export-pdf');
    Route::get('/wastage/export/pdf', [WastageEntryController::class, 'exportPdf'])->name('wastage.export-pdf');
    Route::get('/rnd/export/pdf', [RndEntryController::class, 'exportPdf'])->name('rnd.export-pdf');
    Route::get('/staff-meals/export/pdf', [StaffMealController::class, 'exportPdf'])->name('staff-meals.export-pdf');

    Route::get('/support', [SupportController::class, 'index'])->name('support.index');
    Route::post('/support', [SupportController::class, 'store'])->middleware('throttle:3,1')->name('support.store');

    // Admin/Owner: review and resolve user-submitted support tickets.
    Route::get('/support-tickets', [SupportTicketController::class, 'index'])->name('support-tickets.index');
    Route::patch('/support-tickets/{ticket}/toggle', [SupportTicketController::class, 'toggleStatus'])->name('support-tickets.toggle');
    Route::patch('/support-tickets/{ticket}/type', [SupportTicketController::class, 'updateType'])->name('support-tickets.type');
    Route::get('/support-tickets/{ticket}/media', [SupportTicketController::class, 'media'])->name('support-tickets.media');

    Route::get('/market-purchases', [MarketPurchaseController::class, 'index'])->name('market-purchases.index');
    Route::post('/market-purchases', [MarketPurchaseController::class, 'store'])->name('market-purchases.store');
    Route::delete('/market-purchases/{marketPurchase}', [MarketPurchaseController::class, 'destroy'])->name('market-purchases.destroy');
    Route::get('/market-purchases/{marketPurchase}/receipt', [MarketPurchaseController::class, 'receipt'])->name('market-purchases.receipt');

    Route::get('/production', [ProductionController::class, 'index'])->name('production.index');
    // One dish at a time, with what each ingredient actually took (see LogProduction).
    Route::get('/production/dish/{recipe}', [ProductionController::class, 'dish'])->name('production.dish');
    Route::post('/production/dish/{recipe}', [ProductionController::class, 'storeDish'])->name('production.dish.store');
    Route::delete('/production/{productionBatch}', [ProductionController::class, 'destroy'])->name('production.destroy');

    Route::get('/daily-report', [DailyReportController::class, 'index'])->name('daily-report.index');
    Route::post('/daily-report', [DailyReportController::class, 'store'])->name('daily-report.store');

    // Peer feedback (replaced the Compliance module): anonymous star ratings
    // between staff; the Owner sees identities and exports the monthly report.
    Route::get('/feedback', [FeedbackController::class, 'index'])->name('feedback.index');
    Route::post('/feedback', [FeedbackController::class, 'store'])->middleware('throttle:10,1')->name('feedback.store');
    Route::get('/feedback/attachments/{feedbackAttachment}', [FeedbackController::class, 'attachment'])->name('feedback.attachment');
    Route::get('/feedback/export/pdf', [FeedbackController::class, 'exportPdf'])->name('feedback.export-pdf');

    // Leave applications: staff apply with supporting documents, Owner decides.
    Route::get('/leave', [LeaveApplicationController::class, 'index'])->name('leave.index');
    Route::post('/leave', [LeaveApplicationController::class, 'store'])->middleware('throttle:10,1')->name('leave.store');
    Route::patch('/leave/{leaveApplication}/approve', [LeaveApplicationController::class, 'approve'])->name('leave.approve');
    Route::patch('/leave/{leaveApplication}/reject', [LeaveApplicationController::class, 'reject'])->name('leave.reject');
    Route::get('/leave/attachments/{leaveAttachment}', [LeaveApplicationController::class, 'attachment'])->name('leave.attachment');

    // About the system: plain-language version history, open to every signed-in user.
    Route::get('/about', [AboutController::class, 'index'])->name('about.index');

    // Pantry/Kitchen stock-take (manual sheet, separate from live inventory).
    // Chefs record; managers review; managers manage the item catalogs.
    // The catalog + create routes are declared before the {stockTake} wildcard.
    Route::get('/stock-take', [StockTakeController::class, 'index'])->name('stock-take.index');
    Route::get('/stock-take/create', [StockTakeController::class, 'create'])->name('stock-take.create');
    Route::post('/stock-take', [StockTakeController::class, 'store'])->middleware('throttle:20,1')->name('stock-take.store');
    Route::get('/stock-take/items', [StockTakeItemController::class, 'index'])->name('stock-take-items.index');
    Route::post('/stock-take/items', [StockTakeItemController::class, 'store'])->name('stock-take-items.store');
    Route::patch('/stock-take/items/{stockTakeItem}', [StockTakeItemController::class, 'update'])->name('stock-take-items.update');
    Route::delete('/stock-take/items/{stockTakeItem}', [StockTakeItemController::class, 'destroy'])->name('stock-take-items.destroy');
    Route::get('/stock-take/{stockTake}', [StockTakeController::class, 'show'])->name('stock-take.show');

    // Inventory tally check (manual count against live inventory; variance for the
    // Owner to review). Chefs record; managers + Owner view. Never writes to stock.
    // The create route is declared before the {inventoryTally} wildcard.
    Route::get('/tally', [InventoryTallyController::class, 'index'])->name('tally.index');
    Route::get('/tally/create', [InventoryTallyController::class, 'create'])->name('tally.create');
    Route::post('/tally', [InventoryTallyController::class, 'store'])->middleware('throttle:20,1')->name('tally.store');
    Route::get('/tally/{inventoryTally}', [InventoryTallyController::class, 'show'])->name('tally.show');
    });

require __DIR__.'/auth.php';