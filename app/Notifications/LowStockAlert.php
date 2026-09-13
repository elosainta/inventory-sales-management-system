<?php

namespace App\Notifications;

use App\Models\InventoryItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * An item has run out. Named for the rule it used to carry — the class name is
 * stored in `notifications.type`, so renaming it would hide every alert already
 * sitting unread in a head chef's sidebar.
 */
class LowStockAlert extends Notification
{
    use Queueable;

    public function __construct(public InventoryItem $item) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'inventory_item_id' => $this->item->id,
            'name'              => $this->item->name,
            'quantity_on_hand'  => $this->item->quantity_on_hand,
            'reorder_threshold' => $this->item->reorder_threshold,
            'unit'              => $this->item->unit,
            'message'           => "{$this->item->name} has run out — none left on the shelf.",
        ];
    }
}
