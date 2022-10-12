<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'time',
        'place',
        'status_id',
        'admin_abogado_id',
        'abogado_id',
    ];
}
