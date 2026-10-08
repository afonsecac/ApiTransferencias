<?php

namespace App\Logging;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;

/**
 * RotatingFileHandler que conserva los ficheros por antigüedad, no por número.
 *
 * El tope por número de Monolog (maxFiles) cuenta ficheros, y estos solo existen
 * los días con registros: con poco tráfico, "15 ficheros" pueden abarcar meses.
 * Para un respaldo con datos personales el criterio es la edad: al abrir el
 * fichero de un día nuevo se borran los de más de $maxAgeDays días (la fecha
 * sale del nombre, no de la fecha de modificación, que un backup o un restore
 * pueden alterar).
 */
class AgeLimitedRotatingFileHandler extends RotatingFileHandler
{
    public function __construct(
        string $filename,
        private readonly int $maxAgeDays,
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
    ) {
        // maxFiles = 0: sin borrado por número; la poda la hace rotate() por edad.
        parent::__construct($filename, 0, $level, $bubble);
    }

    protected function rotate(): void
    {
        parent::rotate();

        $cutoff = (new \DateTimeImmutable('today'))
            ->modify(sprintf('-%d days', $this->maxAgeDays))
            ->format('Y-m-d');

        foreach ($this->findRotatedFiles() as $file) {
            if (preg_match('/\.(\d{4}-\d{2}-\d{2})(?:\.[^.\/]+)?$/', basename($file), $matches) === 1
                && $matches[1] < $cutoff
                && is_writable($file)
            ) {
                // Best-effort: dos procesos pueden estar podando a la vez.
                @unlink($file);
            }
        }
    }
}
