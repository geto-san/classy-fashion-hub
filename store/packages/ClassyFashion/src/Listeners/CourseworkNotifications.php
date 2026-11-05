<?php

namespace ClassyFashion\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;

/**
 * Keep coursework notification previews in the private application log.
 */
class CourseworkNotifications
{
    /**
     * Prevent all mail transports from sending when project mode is enabled.
     */
    public function handle(MessageSending $event): ?bool
    {
        if (! config('classy.coursework_mode')) {
            return null;
        }

        Log::info('classy.coursework.notification', [
            'recipients' => array_map(fn ($address) => $address->getAddress(), $event->message->getTo()),
            'subject' => $event->message->getSubject(),
            'body' => $event->message->getTextBody() ?: strip_tags($event->message->getHtmlBody() ?? ''),
        ]);

        return false;
    }
}
