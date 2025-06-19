<?php

namespace App\Traits;

use App\Services\NanoidClientService\NanoidClientService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait HasBootAttribute
{
    protected static function bootHasBootAttribute()
    {
        static::creating(function (Model $model) {
            $identifier = new NanoidClientService();
            $model->{$model->identifierKey} = $model->identifierKeyPrefix . $identifier->generate();

            $hasSlug = Schema::hasColumn($model->getTable(), 'slug');
            if ($hasSlug) {
                $model->slug = Str::slug($model->name);
            }

            $has2charCode = Schema::hasColumn($model->getTable(), '2char_code');
            if ($has2charCode) {
                do {
                    $code = $identifier->formattedId(2);
                } while ($model::where('2char_code', $code)->exists());
                $model->{'2char_code'} = $code;
            }

            if ($model->userIdKey && Auth::check()) {
                $model->{$model->userIdKey} = Auth::id();
            }
        });

        static::created(function ($model) {
            $locationID = Schema::hasColumn($model->getTable(), 'locationID');
            if ($locationID) {
                $model->locationID = 'S-' . str_pad($model->id, 4, '0', STR_PAD_LEFT);
            }
            $locationID = Schema::hasColumn($model->getTable(), 'subsidiaryID');
            if ($locationID) {
                $model->subsidiaryID = 'SB-' . str_pad($model->id, 4, '0', STR_PAD_LEFT);
            }
            $model->save();
        });
    }
}
