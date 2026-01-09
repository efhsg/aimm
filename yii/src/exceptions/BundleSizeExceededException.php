<?php

declare(strict_types=1);

namespace app\exceptions;

/**
 * BundleSizeExceededException is thrown when the total size of assets in a RenderBundle exceeds limits.
 */
final class BundleSizeExceededException extends \RuntimeException
{
}
