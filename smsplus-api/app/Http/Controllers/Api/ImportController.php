<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessImportJob;
use App\Models\Import;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function __construct(
        protected \App\Services\AuditLogService $auditLog,
    ) {}

    public function upload(Request $request)
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls', 'max:51200'],
            'type' => ['required', 'in:occ,mmg'],
        ]);

        $file = $request->file('file');
        $type = $validated['type'];
        $originalName = $file->getClientOriginalName();

        $filename = uniqid().'_'.$originalName;
        $file->storeAs('imports/temp', $filename, 'local');

        $user = $request->attributes->get('auth_user');

        $import = Import::create([
            'filename' => $originalName,
            'type' => $type,
            'status' => 'pending',
            'imported_by' => is_object($user) ? ($user->email ?? $user->name ?? 'inconnu') : 'inconnu',
        ]);

        ProcessImportJob::dispatch($import);

        $this->auditLog->log('import', 'cdr', "Lancement import {$type} : {$originalName}", [], (array)$import, 'succes', $import->id);

        return response()->json([
            'import_id' => $import->id,
            'status' => 'pending',
            'message' => 'Import lancé en arrière-plan',
        ]);
    }

    public function status(int $id)
    {
        $import = Import::findOrFail($id);

        $percentage = 0;
        if ($import->total_rows > 0) {
            $percentage = (int) round(($import->imported_rows / $import->total_rows) * 100);
        } elseif ($import->status === 'done') {
            $percentage = 100;
        }

        $elapsed = null;
        if ($import->started_at) {
            $end = $import->finished_at ?? now();
            $elapsed = $import->started_at->diffInSeconds($end);
        }

        return response()->json([
            'id' => $import->id,
            'filename' => $import->filename,
            'type' => $import->type,
            'status' => $import->status,
            'total_rows' => $import->total_rows,
            'imported_rows' => $import->imported_rows,
            'error_rows' => $import->error_rows,
            'error_message' => $import->error_message,
            'percentage' => $percentage,
            'elapsed_seconds' => $elapsed,
            'started_at' => $import->started_at?->toIso8601String(),
            'finished_at' => $import->finished_at?->toIso8601String(),
        ]);
    }

    public function history()
    {
        $imports = Import::orderByDesc('created_at')->limit(50)->get();

        return response()->json($imports);
    }

    public function destroy(int $id)
    {
        $import = Import::findOrFail($id);

        $tempPath = storage_path("imports/temp/{$import->id}_{$import->filename}");
        if (file_exists($tempPath)) {
            unlink($tempPath);
        }

        $import->delete();

        $this->auditLog->log('delete', 'import', "Suppression historique import #{$id} ({$import->filename})", (array)$import, [], 'succes', $id);

        return response()->json(['message' => 'Import supprimé']);
    }
}
