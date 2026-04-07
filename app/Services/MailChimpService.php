<?php

namespace App\Services;

use App\Models\MailingList;
use MailchimpMarketing\ApiClient;

class MailChimpService
{
    public function isConfigured(): bool
    {
        return filled(config('services.mailchimp.api_key'))
            && filled(config('services.mailchimp.server_prefix'))
            && filled(config('services.mailchimp.audience_id'));
    }

    public function syncList(MailingList $list): void
    {
        $client     = $this->client();
        $audienceId = config('services.mailchimp.audience_id');
        $tagName    = $list->name;

        $list->contacts()
            ->with('tags')
            ->chunk(500, function ($contacts) use ($client, $audienceId, $tagName) {
                $upsertOps = [];
                $tagOps    = [];

                foreach ($contacts as $contact) {
                    if (empty($contact->email)) {
                        continue;
                    }

                    $hash = md5(strtolower(trim($contact->email)));

                    $upsertOps[] = [
                        'method' => 'PUT',
                        'path'   => "/lists/{$audienceId}/members/{$hash}",
                        'body'   => json_encode([
                            'status_if_new' => 'subscribed',
                            'email_address' => $contact->email,
                            'merge_fields'  => [
                                'FNAME' => $contact->first_name ?? '',
                                'LNAME' => $contact->last_name ?? '',
                            ],
                        ]),
                    ];

                    $tagOps[] = [
                        'method' => 'POST',
                        'path'   => "/lists/{$audienceId}/members/{$hash}/tags",
                        'body'   => json_encode([
                            'tags' => [['name' => $tagName, 'status' => 'active']],
                        ]),
                    ];
                }

                if (! empty($upsertOps)) {
                    $client->batches->start(['operations' => $upsertOps]);
                }

                if (! empty($tagOps)) {
                    $client->batches->start(['operations' => $tagOps]);
                }
            });
    }

    public function removeTag(string $email, string $tag): void
    {
        $client     = $this->client();
        $audienceId = config('services.mailchimp.audience_id');
        $hash       = md5(strtolower(trim($email)));

        $client->lists->updateListMemberTags($audienceId, $hash, [
            'tags' => [['name' => $tag, 'status' => 'inactive']],
        ]);
    }

    private function client(): ApiClient
    {
        $client = new ApiClient();
        $client->setConfig([
            'apiKey' => config('services.mailchimp.api_key'),
            'server' => config('services.mailchimp.server_prefix'),
        ]);

        return $client;
    }
}
