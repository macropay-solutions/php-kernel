<?php

namespace MacropaySolutions\Kernel\Http\Base;

use MacropaySolutions\Kernel\Http\FileHelpers;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;

/**
 * Use this class instead of its parent in your code base!!!
 */
class File extends SymfonyFile
{
    use FileHelpers;
}
