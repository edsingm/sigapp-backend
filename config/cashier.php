<?php

use Laravel\Cashier\Invoices\DompdfInvoiceRenderer;

return [

    /*
    |--------------------------------------------------------------------------
    | Stripe Keys
    |--------------------------------------------------------------------------
    |
    | The Stripe publishable key and secret key give you access to Stripe's
    | API. The "publishable" key is typically used when interacting with
    | Stripe.js while the "secret" key accesses private API endpoints.
    |
    */

    'key' => env('STRIPE_KEY'),

    'secret' => env('STRIPE_SECRET'),

    /*
    | IDs dos Prices recorrentes por ambiente. Quando ausentes, o checkout
    | cria um Price emergencial com o valor configurado no plano.
    */
    'plan_prices' => [
        'broker' => env('STRIPE_PRICE_BROKER'),
        'basico' => env('STRIPE_PRICE_BASICO'),
        'master' => env('STRIPE_PRICE_MASTER'),
        'pro' => env('STRIPE_PRICE_PRO'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cashier Path
    |--------------------------------------------------------------------------
    |
    | This is the base URI path where Cashier's views, such as the payment
    | verification screen, will be available from. You're free to tweak
    | this path according to your preferences and application design.
    |
    */

    'path' => env('CASHIER_PATH', 'stripe'),

    /*
    |--------------------------------------------------------------------------
    | Stripe Webhooks
    |--------------------------------------------------------------------------
    |
    | Your Stripe webhook secret is used to prevent unauthorized requests to
    | your Stripe webhook handling controllers. The tolerance setting will
    | check the drift between the current time and the signed request's.
    |
    */

    'webhook' => [
        'secret' => env('STRIPE_WEBHOOK_SECRET'),
        'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),

        // Eventos explícitos tratados pelo WebhookController. Mantenha em sincronia
        // com os handlers em App\Http\Controllers\Api\V1\WebhookController.
        'events' => [
            'checkout.session.completed',
            'invoice.paid',
            'invoice.payment_failed',
            'invoice.payment_action_required',
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted',
            'customer.subscription.trial_will_end',
            'charge.dispute.created',
            'charge.dispute.updated',
            'charge.dispute.closed',
            'customer.discount.created',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Customer Portal Configuration
    |--------------------------------------------------------------------------
    |
    | ID de uma configuração de Customer Portal do Stripe (bpc_...) com a troca
    | de plano DESABILITADA. Quando definido, é enviado explicitamente ao criar
    | a sessão do portal, impedindo que o cliente troque de plano por fora do
    | PlanSwapController (que classifica upgrade/downgrade). Vazio => usa a
    | configuração default do Dashboard.
    |
    */

    'portal_configuration_id' => env('STRIPE_PORTAL_CONFIGURATION_ID'),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | This is the default currency that will be used when generating charges
    | from your application. Of course, you are welcome to use any of the
    | various world currencies that are currently supported via Stripe.
    |
    */

    'currency' => env('CASHIER_CURRENCY', 'brl'),

    /*
    |--------------------------------------------------------------------------
    | Currency Locale
    |--------------------------------------------------------------------------
    |
    | This is the default locale in which your money values are formatted in
    | for display. To utilize other locales besides the default en locale
    | verify you have the "intl" PHP extension installed on the system.
    |
    */

    'currency_locale' => env('CASHIER_CURRENCY_LOCALE', 'pt_BR'),

    /*
    |--------------------------------------------------------------------------
    | Payment Confirmation Notification
    |--------------------------------------------------------------------------
    |
    | If this setting is enabled, Cashier will automatically notify customers
    | whose payments require additional verification. You should listen to
    | Stripe's webhooks in order for this feature to function correctly.
    |
    */

    'payment_notification' => env('CASHIER_PAYMENT_NOTIFICATION'),

    /*
    |--------------------------------------------------------------------------
    | Invoice Settings
    |--------------------------------------------------------------------------
    |
    | The following options determine how Cashier invoices are converted from
    | HTML into PDFs. You're free to change the options based on the needs
    | of your application or your preferences regarding invoice styling.
    |
    */

    'invoices' => [
        'renderer' => env('CASHIER_INVOICE_RENDERER', DompdfInvoiceRenderer::class),

        'options' => [
            // Supported: 'letter', 'legal', 'A4'
            'paper' => env('CASHIER_PAPER', 'A4'),

            'remote_enabled' => env('CASHIER_REMOTE_ENABLED', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe Logger
    |--------------------------------------------------------------------------
    |
    | This setting defines which logging channel will be used by the Stripe
    | library to write log messages. You are free to specify any of your
    | logging channels listed inside the "logging" configuration file.
    |
    */

    'logger' => env('CASHIER_LOGGER'),

];
