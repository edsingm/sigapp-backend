<?php

namespace App\Models\Central\Modules;

use App\Enums\Common\ModulesEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Modules extends Model
{
    use CentralConnection, HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'modules';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'id',
        'slug',
        'icon',
        'resources',
        'description',
        'active',
        'order',
        'created_at',
        'updated_at',
    ];

    protected $appends = ['sector', 'submodules'];

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => ModulesEnum::from($this->slug)->label(),
        );
    }

    protected function sector(): Attribute
    {
        return Attribute::make(
            get: fn () => ModulesEnum::from($this->slug)->sector(),
        );
    }

    protected function submodules(): Attribute
    {
        return Attribute::make(
            get: fn () => ModulesEnum::from($this->slug)->submodules(),
        );
    }
}
