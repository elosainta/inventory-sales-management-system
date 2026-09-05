<?php

namespace App\Notifications;

use App\Models\InventoryItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

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
            'message'           => "{$this->item->name} is low on stock ({$this->item->quantity_on_hand} {$this->item->unit} remaining).",
        ];
    }
}
