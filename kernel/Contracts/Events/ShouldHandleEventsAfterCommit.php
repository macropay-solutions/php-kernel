<?php

namespace MacropaySolutions\Kernel\Contracts\Events;

/**
 * This should be used for created event.
 * If you need it for updated event you should NOT use this interface, instead you should use:
 *
 *  namespace App\Observers;
 *
 *  use App\Models\User;
 *
 *  class Observer
 *  {
 *      public function updated(User $model): void
 *      {
 *          $original = $model->getOriginal();
 *          $changes = $model->getChanges();
 *
 *          \app('db')->afterCommit(function() use ($model, $original, $changes): void {
 *              //send email or do something.
 *          });
 *      }
 *  }
 * Thanks to https://github.com/JaredMezz2
 *
 * The following events will not be affected by this interface:
 *  'creating',
 *  'updating',
 *  'saving',
 *  'restoring',
 *  'replicating',
 *  'deleting',
 *  'forceDeleting',
 */
interface ShouldHandleEventsAfterCommit
{
    //
}
