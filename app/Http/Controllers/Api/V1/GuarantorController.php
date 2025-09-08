<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\GuarantorResponseRequest;
use App\Models\Guarantor;
use App\Models\Loan;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Events\LoanApplicantNotified;

class GuarantorController extends Controller
{
    public function respond(GuarantorResponseRequest $request, string $guarantorId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $loan = Loan::findOrFail($request->loan_id);

            // Update the pivot record using guaranteedBy relationship
            $updated = DB::table('loan_guarantors')
                ->where('loan_id', $request->loan_id)
                ->where('guarantor_id', $guarantorId)
                ->update([
                    'status' => $request->response,
                    'response' => $validated['reason'] ?? null,
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                throw new \Exception('Guarantor is not associated with this loan or already responded');
            }

            // Update specific notification by ID
            Notification::where('id', $request->notification_id)->update([
                'read_at' => now(),
                'is_read' => 1,
            ]);

            DB::commit();

            // Dispatch event after successful transaction
            LoanApplicantNotified::dispatch(
                $loan,
                $request->response,
                $guarantorId,
            );

            return response()->json([
                'success' => true,
                'message' => 'Response recorded successfully',
                'data' => [
                    'id' => $request->notification_id,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Guarantor response failed: " . $e->getMessage(). $e->getFile());
            return response()->json([
                'success' => false,
                'message' => 'Failed to process response: Message '. $e->getMessage() .' '. $e->getFile() .' '. $e->getLine(),
                'data' => $e->getTrace()
            ], 500);
        }
    }
}
