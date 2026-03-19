<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MailChimpWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        if ($request->query('secret') !== config('services.mailchimp.webhook_secret')) {
            abort(403);
        }

        $type  = $request->input('type');
        $data  = json_decode($request->input('data', '{}'), true);
        $email = $data['email'] ?? null;

        if ($type === 'unsubscribe' && $email) {
            Contact::where('email', $email)->update(['mailing_list_opt_in' => false]);
        }

        return response('OK', 200);
    }
}
