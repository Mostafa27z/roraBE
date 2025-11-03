<?php

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Auth\Database\Factories\RoleFactory;
use App\Models\User;

class Role extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'role_user');
    }
    
    /**
     * ✅ Create a new factory instance for the model.
     */
    
    protected static function newFactory()
    {
        return RoleFactory::new();
    }

}