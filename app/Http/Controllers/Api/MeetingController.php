<?php

namespace App\Http\Controllers\Api;

use Exception;
use App\Models\User;
use App\Models\Meeting;
use App\Enums\MeetingEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Notifications\MeetingNotification;
use App\Http\Requests\Api\StoreMettingRequest;

class MeetingController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:meetings.index')->only('index');
        $this->middleware('can:meetings.store')->only('store');
        $this->middleware('can:meetings.show')->only('show');
        $this->middleware('can:meetings.update')->only('update');
        $this->middleware('can:meetings.destroy')->only('destroy');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $meetings = Meeting::all();

        return response()->json([
            'message' => 'Listado de reuniones',
            'meetings' => $meetings
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreMettingRequest $request)
    {
        $parts = $this->getParts($request);

        foreach ($parts as $key => $value) {
            $user = User::find($value);

            if($value && !isset($user)) {
                return response()->json([
                    'message' => "El usuario propuesto como $key no esta registrado en el sistema",
                ]);
            }

            if($value && ! $user->hasRole($key)) {
                return response()->json([
                    'message' => "El usuario $value propuesto como $key no tiene el rol necesario",
                ]);
            }
        }

        DB::beginTransaction();

        try {
            $meeting = Meeting::create([
                "place" => $request->place,
                "date" => $request->date,
                "time" => $request->time,
                "status_id" => 1, //MeetingEnum::Programada,
                "abogado_id" => $request->abogado_id,
                "admin_abogado_id" => auth()->user()->id
            ]);

            $meeting->parts()->create(['part_id' => $request->arrendador_id, 'role' => 'arrendador']);
            $meeting->parts()->create(['part_id' => $request->arrendatario_id, 'role' => 'arrendatario']);
            if($request->has('solidario_id')) $meeting->parts()->create(['part_id' => $request->solidario_id, 'role' => 'solidario']);
            if($request->has('fiador_id')) $meeting->parts()->create(['part_id' => $request->fiador_id, 'role' => 'fiador']);

            DB::commit();

            $this->notifyParts($parts, $meeting);

            return response()->json([
                'message' => 'Reunión programada correctamente',
                'meeting' => $meeting->load('parts', 'status', 'abogado'),
            ]);

        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Ocurrió un error y no se pudo registrar la reunión',
                'error' => $e->getMessage()
            ]);
        } 
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Meeting  $meeting
     * @return \Illuminate\Http\Response
     */
    public function show(Meeting $meeting)
    {
        return response()->json([
            'message' => 'Detalle de una reunión',
            'meeting' => $meeting->load('parts', 'meetingChanges', 'status', 'abogado')
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Meeting  $meeting
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Meeting $meeting)
    {
        $request->validate([
            'reason' => 'required|max:255'
        ]);

        if($request->has('abogado_id')) {
            $abogado = User::find($request->abogado_id);

            if(!isset($abogado)) {
                return response()->json([
                    'message' => "El nuevo abogado no esta registrado en el sistema",
                ]);
            }

            if(!$abogado->hasRole('abogado')) {
                return response()->json([
                    'message' => "El usuario propuesto como abogado no tiene ese rol",
                ]);
            }
        }

        $meetingOldData = $meeting->toArray();

        $meeting->update(array_merge($request->all(), ['status_id' => 2]));

        foreach($meeting->getChanges() as $key => $value) {
            $meeting->meetingChanges()->create([
                'reason' => $request->reason,
                'field' => $key,
                'old_value' => $meetingOldData[$key],
                'new_value' => $value,
            ]);
        }

        $meeting->reason = $request->reason;

        $this->notifyParts([
            'abogado' => $meeting->abogado->id ?? null,
            'arrendador' => $meeting->parts[0]->arrendador->id ?? null,
            'arrendatario' => $meeting->parts[1]->arrendatario->id ?? null,
            'solidario' => $meeting->solidario->parts[2]->id ?? null,
            'fiador' => $meeting->parts[3]->fiador->id ?? null,
        ], $meeting);

        return response()->json([
            'message' => 'La reunión fue actualizada de forma correcta',
            'meetings' => $meeting->load('parts', 'meetingChanges', 'status', 'abogado'),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Meeting  $meeting
     * @return \Illuminate\Http\Response
     */
    public function destroy(Meeting $meeting)
    {
        //
    }

    private function getParts($request)
    {
        $parts = [
            'abogado' => $request->abogado_id,
            'arrendador' => $request->arrendador_id,
            'arrendatario' => $request->arrendatario_id,
            'solidario' => $request->solidario_id ?? null,
            'fiador' => $request->fiador_id ?? null,
        ];

        return $parts;
    }

    private function notifyParts($parts, $meeting)
    {
        foreach ($parts as $key => $value) {
            $user = User::find($value);

            if(isset($user)) {
                $user->notify(new MeetingNotification($meeting));
            }
        }
    }
}
