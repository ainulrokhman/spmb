<?php

namespace App\Services\Whatsapp;

use RuntimeException;

/**
 * A WhatsApp gateway call failed: the provider was unreachable,
 * rejected the credentials, or refused the message. Callers
 * decide what the failure means (the sender service logs it and
 * reports the message as failed or skipped); the exception
 * message is always admin-readable and safe to show.
 */
class WhatsappGatewayException extends RuntimeException
{
}
