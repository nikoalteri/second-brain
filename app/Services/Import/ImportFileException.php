<?php

namespace App\Services\Import;

use RuntimeException;

/** A statement file that cannot be accepted; the message is safe to show to the uploader. */
class ImportFileException extends RuntimeException {}
