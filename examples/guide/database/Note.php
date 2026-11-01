<?php

namespace OktaaxExamples\Database;

use Oktaax\Database\Eloquent\Model;

/**
 * Notes use the active Oktaax request's connection through the base model.
 *
 * @property int $id
 * @property string $title
 * @property string $body
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class Note extends Model
{
    protected $table = 'notes';
    protected $fillable = ['title', 'body'];
    protected $casts = ['id' => 'integer'];
}
