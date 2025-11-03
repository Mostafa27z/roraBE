<?php

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RoleUser extends Model
{
    use HasFactory;

    protected $table = 'role_user'; // explicitly define the table name

    public $timestamps = false; // pivot tables usually don't need timestamps

    protected $fillable = [
        'user_id',
        'role_id',
    ];
}
