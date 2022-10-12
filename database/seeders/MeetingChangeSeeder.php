<?php

namespace Database\Seeders;

use App\Models\Meeting;
use App\Models\MeetingChange;
use Illuminate\Database\Seeder;

class MeetingChangeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $meeting1 = Meeting::find(1);
        $oldDate = $meeting1->date;
        $oldPlace = $meeting1->place;
        $oldStatus = $meeting1->status_id;
        $meeting1->date = date("Y-m-d", time() + (86400 * 7));
        $meeting1->place = "Reforma 32";
        $meeting1->status_id = 2;
        $meeting1->save();

        MeetingChange::create([
            'meeting_id' => $meeting1->id,
            'reason' => 'Se cambió la fecha a solicitud del arrendador',
            'field' => 'date',
            'old_value' => $oldDate,
            'new_value' => $meeting1->date,
        ]);

        MeetingChange::create([
            'meeting_id' => $meeting1->id,
            'reason' => 'Se cambió el lugar a solicitud del arrendador',
            'field' => 'place',
            'old_value' => $oldPlace,
            'new_value' => $meeting1->place,
        ]);

        MeetingChange::create([
            'meeting_id' => $meeting1->id,
            'reason' => 'Se cambió el lugar a solicitud del arrendador',
            'field' => 'status_id',
            'old_value' => $oldStatus,
            'new_value' => $meeting1->status_id,
        ]);
    }
}
