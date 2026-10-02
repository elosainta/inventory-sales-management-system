<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'contact', 'email', 'address', 'bukku_contact_id'];

    /**
     * The kitchen supplier for a Bukku supplier: the linked one first, else an
     * unlinked one with the same name (which is then linked), else a new one.
     *
     * The link is what keeps the Suppliers page to one entry per company.
     * Matching on name alone created a second "RIVERSIDE" for every
     * scan, because Bukku spells it out in full.
     */
    public static function forBukkuContact(?int $contactId, ?string $name): self
    {
        if ($contactId && $linked = self::where('bukku_contact_id', $contactId)->first()) {
            return $linked;
        }

        $name = trim((string) $name) ?: 'Unknown supplier';

        // contact, email and address are NOT NULL, and an invoice carries none
        // of them. Empty is the honest value — the Owner fills them in.
        $supplier = self::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
            ?? self::create(['name' => $name, 'contact' => '', 'email' => '', 'address' => '']);

        if ($contactId && ! $supplier->bukku_contact_id) {
            $supplier->update(['bukku_contact_id' => $contactId]);
        }

        return $supplier;
    }
}
