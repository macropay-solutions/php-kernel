<?php

namespace MacropaySolutions\Kernel\Notifications;

trait Notifiable
{
    use HasDatabaseNotifications;
    use RoutesNotifications;
}
