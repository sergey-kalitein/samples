<?php

namespace App\Helpers;

use App\Models\Engagement;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Laravel\Cashier\Exceptions\PaymentActionRequired;
use Laravel\Cashier\Exceptions\PaymentFailure;
use Laravel\Cashier\Invoice;

class PaymentHelper
{
    /**
     * Retrieves the user payment methods in the following format:
     * $response = [
     *      [
     *          'payment_method_id' => (int) Payment method ID,
     *          'id_default'        => (bool) Whether the method is the default one,
     *          'brand'             => (string) The CC brand,
     *          'exp_month'         => (string(2)) The CC Expiration Month,
     *          'exp_year'          => (string(2)) The CC Expiration Year,
     *          'last4'             => (string(4)) The last 4 digits of the CC number,
     *      ]
     *  ];
     *
     * @param $user
     *
     * @return array
     */
    public static function getPaymentMethods($user): array
    {
        $response = [];
        $paymentDefault = $user->defaultPaymentMethod();
        if ($paymentDefault !== null) {
            $paymentDefault = $paymentDefault->asStripePaymentMethod();
        }

        $payments = $user->paymentMethods();
        foreach ($payments as $payment) {
            $card = $payment->asStripePaymentMethod()->card;
            $is_default = false;
            if ($paymentDefault !== null && $paymentDefault->id === $payment->asStripePaymentMethod()->id) {
                $is_default = true;
            }
            $response[] = [
                'payment_method_id' => $payment->asStripePaymentMethod()->id,
                'id_default' => $is_default,
                'brand' => $card->brand,
                'exp_month' => $card->exp_month,
                'exp_year' => $card->exp_year,
                'last4' => $card->last4,
            ];
        }

        return $response;
    }

    /**
     * Creates an engagement invoice.
     *
     * @param int $engagementId The engagement ID to created an invoice for
     *
     * @return bool Whether the creation was successful
     * @throws PaymentActionRequired
     * @throws PaymentFailure
     */
    public static function createEngagementInvoice(int $engagementId): bool
    {
        if (config('app.fee_prc') !== null) {
            /** @var Engagement $engagement */
            $engagement = Engagement::query()->where('id', $engagementId)->with('project')->with('expert')->first();
            /** @var User $user */
            $user = User::query()->where('company_id', $engagement->project->company_id)->where(
                'user_type',
                'company_owner'
            )->first();

            /** @var User $receiver */
            $receiver = User::query()->where('id', $engagement->expert->user_id)->first();

            $invoiceTitle = 'Payment to '
                . $engagement->expert->name
                . ' for the project '
                . $engagement->project->name
                . ' engagement '
                . $engagement->name;

            $amount = $engagement->last_rate * 100;
            $invoiceMetaData = self::buildInvoiceMetadata($engagement, $user, $receiver, $amount);

            $userPaymentMethods = self::getPaymentMethods($user);
            self::chargeAmount($user, $receiver, $amount, $userPaymentMethods[0], $invoiceMetaData, $invoiceTitle);

            NotificationHelper::paymentEngagementSuccess(
                [
                    'user_id' => $user->id,
                    'data' => [],
                ]
            );
            return true;
        }
        return false;
    }

    /**
     * Generates payment history on per a user
     *
     * @param User $user
     *
     * @return array
     */
    public static function getPaymentsHistory(User $user)
    {
        $response = [];
        $subs = [];

        $invoices = $user->invoicesIncludingPending();

        /** @var Invoice $invoice */
        foreach ($invoices as $invoice) {
            /** @var \Stripe\Invoice $invoiceStripe */
            $invoiceStripe = $invoice->asStripeInvoice();
            $subscription = Subscription::query()->where('stripe_id', $invoiceStripe->subscription)->first();
            if ($subscription !== null) {
                $subs[$subscription->stripe_plan] = SubscriptionPlan::select(
                    'id',
                    'title',
                    'description',
                    'staff_amount',
                    'price'
                )->where('stripe_id', $subscription->stripe_plan)->first();
            }
            $response[] = [
                'created' => $invoiceStripe->created,
                'number' => $invoiceStripe->number,
                'paid' => $invoiceStripe->paid,
                'total' => $invoiceStripe->total,
                'invoice_pdf' => $invoiceStripe->invoice_pdf,
                'subscription' => $subscription !== null && isset($subs[$subscription->stripe_plan])
                    ? $subs[$subscription->stripe_plan] : null,
            ];
        }

        return $response;
    }

    /**
     * Calculates the fee for the given amount.
     *
     * @param float|int $amount The basic amount to get fee for
     *
     * @return float The calculated fee
     */
    private static function getFeeForAmount(float|int $amount): float
    {
        return round($amount * (float)config('app.fee_prc') / 100);
    }

    /**
     * @param Engagement $engagement
     * @param User $user
     * @param User $receiver
     * @param float $amount
     *
     * @return array
     */
    private static function buildInvoiceMetadata(
        Engagement $engagement,
        User $user,
        User $receiver,
        float $amount
    ): array {
        return [
            'engagement_id' => $engagement->id,
            'engagement_name' => $engagement->name,
            'project_id' => $engagement->project->id,
            'project_name' => $engagement->project->name,
            'sender_id' => $user->id,
            'sender_email' => $user->email,
            'receiver_id' => $receiver->id,
            'receiver_email' => $receiver->email,
            'fee' => self::getFeeForAmount($amount),
        ];
    }

    /**
     * @param User $user
     * @param User $receiver
     * @param float $amount
     * @param $userPaymentMethods
     * @param array $invoiceMetaData
     * @param string $invoiceTitle
     */
    private static function chargeAmount(
        User $user,
        User $receiver,
        float $amount,
        $userPaymentMethods,
        array $invoiceMetaData,
        string $invoiceTitle
    ): void {
        if ($receiver->stripe_id) {
            $user->charge(
                $amount,
                $userPaymentMethods['payment_method_id'],
                [
                    'description' => $invoiceTitle,
                    'application_fee_amount' => self::getFeeForAmount($amount),
                    'metadata' => $invoiceMetaData,
                    'transfer_data' => [
                        'destination' => $receiver->stripe_id,
                    ],
                ]
            );
        } else {
            $user->charge(
                $amount,
                $userPaymentMethods['payment_method_id'],
                [
                    'description' => $invoiceTitle . ' (w/o transfer)',
                    'metadata' => $invoiceMetaData,
                ]
            );
        }
    }
}
