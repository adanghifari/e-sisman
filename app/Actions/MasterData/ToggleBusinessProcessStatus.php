<?php

namespace App\Actions\MasterData;

use App\Models\BusinessProcess;

class ToggleBusinessProcessStatus
{
    public function handle(BusinessProcess $businessProcess): BusinessProcess
    {
        $businessProcess->update([
            'is_active' => ! $businessProcess->is_active,
        ]);

        return $businessProcess->refresh();
    }
}
