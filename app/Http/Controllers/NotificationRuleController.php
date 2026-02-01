<?php

namespace App\Http\Controllers;

use App\Models\NotificationRule;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationRuleController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * List all notification rules for the authenticated user
     */
    public function index(Request $request)
    {
        $rules = NotificationRule::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($rules);
    }

    /**
     * Create a new notification rule
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'event_type' => 'required|in:expense_created,income_created,loan_created,loan_payment_due,daily_summary,weekly_summary,monthly_summary',
            'conditions' => 'nullable|array',
            'delivery_channel' => 'required|in:telegram',
            'telegram_topic_id' => 'nullable|string',
            'message_template' => 'nullable|string',
            'is_active' => 'boolean',
            'schedule_time' => 'nullable|date_format:H:i',
            'schedule_frequency' => 'required|in:immediate,daily,weekly,monthly',
            'schedule_day' => 'nullable|string',
        ]);

        $validated['user_id'] = $request->user()->id;

        $rule = NotificationRule::create($validated);

        return response()->json($rule, 201);
    }

    /**
     * Update an existing notification rule
     */
    public function update(Request $request, $id)
    {
        $rule = NotificationRule::where('user_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'event_type' => 'sometimes|in:expense_created,income_created,loan_created,loan_payment_due,daily_summary,weekly_summary,monthly_summary',
            'conditions' => 'nullable|array',
            'delivery_channel' => 'sometimes|in:telegram',
            'telegram_topic_id' => 'nullable|string',
            'message_template' => 'nullable|string',
            'is_active' => 'boolean',
            'schedule_time' => 'nullable|date_format:H:i',
            'schedule_frequency' => 'sometimes|in:immediate,daily,weekly,monthly',
            'schedule_day' => 'nullable|string',
        ]);

        $rule->update($validated);

        return response()->json($rule);
    }

    /**
     * Delete a notification rule
     */
    public function destroy(Request $request, $id)
    {
        $rule = NotificationRule::where('user_id', $request->user()->id)->findOrFail($id);
        $rule->delete();

        return response()->json(['message' => 'Rule deleted successfully']);
    }

    /**
     * Test a notification rule (send test notification)
     */
    public function test(Request $request, $id)
    {
        $rule = NotificationRule::where('user_id', $request->user()->id)->findOrFail($id);

        // Generate test data based on event type
        $testData = $this->generateTestData($rule->event_type);

        // Send notification
        $this->notificationService->send($rule, $testData);

        return response()->json(['message' => 'Test notification sent']);
    }

    /**
     * Generate test data for notification testing
     */
    private function generateTestData(string $eventType): array
    {
        return match($eventType) {
            'expense_created' => [
                'amount' => 1500.00,
                'description' => 'Test Expense',
                'category' => ['name' => 'Food'],
                'date' => now()->format('Y-m-d'),
                'payment_source' => 'Cash'
            ],
            'income_created' => [
                'amount' => 5000.00,
                'description' => 'Test Income',
                'category' => ['name' => 'Salary'],
                'date' => now()->format('Y-m-d')
            ],
            'loan_created' => [
                'amount' => 10000.00,
                'description' => 'Test Loan'
            ],
            'daily_summary', 'weekly_summary', 'monthly_summary' => [
                'total_expenses' => 12500.00,
                'total_income' => 50000.00,
                'balance' => 37500.00,
                'period' => 'Test Period'
            ],
            default => []
        };
    }
}
