<?php

namespace KaziOmar\SmartUpload\Exceptions;

/**
 * Class SecurityException
 *
 * Thrown when a file violates security policies (MIME spoofing, banned extensions, path traversal).
 */
class SecurityException extends UploadException
{
}
