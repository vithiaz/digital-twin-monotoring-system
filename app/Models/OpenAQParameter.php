<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpenAQParameter extends Model
{
    use HasFactory;

    protected $table = 'openaq_parameters';

    protected $fillable = [
        'openaq_id',
        'name',
        'display_name',
        'units',
        'description',
    ];
}
