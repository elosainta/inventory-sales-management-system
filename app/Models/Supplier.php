<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'contact', 'email', 'address'];
}