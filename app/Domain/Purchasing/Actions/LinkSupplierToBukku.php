<?php

namespace App\Domain\Purchasing\Actions;

use App\Models\Supplier;
use App\Support\Bukku;
use RuntimeException;

/**
 * The Bukku supplier (contact) for one of the kitchen's own suppliers,
 * registering it in Bukku if it is not there yet.
 *
 * The Suppliers page is the source now (2026-09-22, the Owner): a supplier
 * added on the website is pickable on the review screen straight away, and
 * only gets a Bukku contact when a bill is first sent under it. Before this
 * the review screen listed Bukku's contacts, so a supplier added here could
 * not be picked until someone also made it in Bukku - and then an hour's
 * cache hid it.
 *
 * In order: the saved link; else a Bukku supplier with the same name, read
 * fresh (never create a second contact for one someone just made by hand);
 * else create one. Whichever it is, the link is saved on the supplier.
 */
class LinkSupplierToBukku
{
    public function execute(Supplier $supplier): int
    {
        if ($supplier->bukku_contact_id) {
            return (int) $supplier->bukku_contact_id;
        }

        $contactId = Bukku::contactIdNamed($supplier->name);

        if (! $contactId) {
            // The list is cached for an hour; one made in Bukku since would be
            // missed and created twice.
            Bukku::forgetContacts();
            $contactId = Bukku::contactIdNamed($supplier->name) ?? Bukku::createSupplier($supplier->name);
        }

        // One Bukku supplier is one kitchen supplier (unique index). If another
        // already holds this contact, the reviewer picked the wrong duplicate.
        $holder = Supplier::where('bukku_contact_id', $contactId)->whereKeyNot($supplier->id)->value('name');

        if ($holder) {
            throw new RuntimeException('"' . $supplier->name . '" is the same Bukku supplier as "' . $holder . '" - pick "' . $holder . '" instead.');
        }

        $supplier->update(['bukku_contact_id' => $contactId]);

        return $contactId;
    }
}
