<?php

declare(strict_types=1);

namespace App\Market\Violation\Application;

use App\Service\CommonService;

/** Fixture: el código nuevo no puede extender CommonService. */
abstract class ExtiendeCommonService extends CommonService
{
}
