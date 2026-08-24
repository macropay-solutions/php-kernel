<?php

namespace MacropaySolutions\Kernel\Notifications;

use MacropaySolutions\Kernel\Database\Obvious\Builder;
use MacropaySolutions\Kernel\Database\Obvious\Model;

class DatabaseNotification extends Model
{
    public const READ_AT_FORMAT = 'Y-m-d H:i:s';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'notifications';

    /**
     * The guarded attributes on the model.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    /**
     * Get the notifiable entity that the notification belongs to.
     *
     * @return \MacropaySolutions\Kernel\Database\Obvious\Relations\MorphTo
     */
    public function notifiable()
    {
        return $this->morphTo();
    }

    /**
     * Mark the notification as read.
     *
     * @return void
     */
    public function markAsRead()
    {
        if (is_null($this->read_at)) {
            $this->forceFill(['read_at' => \date($this::READ_AT_FORMAT)])->save();
        }
    }

    /**
     * Mark the notification as unread.
     *
     * @return void
     */
    public function markAsUnread()
    {
        if (!is_null($this->read_at)) {
            $this->forceFill(['read_at' => null])->save();
        }
    }

    /**
     * Determine if a notification has been read.
     *
     * @return bool
     */
    public function read()
    {
        return $this->read_at !== null;
    }

    /**
     * Determine if a notification has not been read.
     *
     * @return bool
     */
    public function unread()
    {
        return $this->read_at === null;
    }

    /**
     * Scope a query to only include read notifications.
     *
     * @param \MacropaySolutions\Kernel\Database\Obvious\Builder $query
     * @return \MacropaySolutions\Kernel\Database\Obvious\Builder
     */
    public function scopeRead(Builder $query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Scope a query to only include unread notifications.
     *
     * @param \MacropaySolutions\Kernel\Database\Obvious\Builder $query
     * @return \MacropaySolutions\Kernel\Database\Obvious\Builder
     */
    public function scopeUnread(Builder $query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Create a new database notification collection instance.
     *
     * @param array $models
     * @return \MacropaySolutions\Kernel\Notifications\DatabaseNotificationCollection
     */
    public function newCollection(array $models = [])
    {
//        return new DatabaseNotificationCollection($models);
        return \di(DatabaseNotificationCollection::class, [$models]);
    }
}
