<?php

use App\Filament\Resources\ContactResource\Pages\EditContact;
use App\Filament\Widgets\RecordDetailViewWidget;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\WidgetTypeSeeder())->run();
    (new \Database\Seeders\PermissionSeeder())->run();
});

it('declares RecordDetailViewWidget on EditContact::getFooterWidgets()', function () {
    $page = new EditContact();
    $method = (new ReflectionClass($page))->getMethod('getFooterWidgets');
    $method->setAccessible(true);

    expect($method->invoke($page))->toBe([RecordDetailViewWidget::class]);
});

it('renders the placeholder string when the Filament widget is mounted with a Contact record', function () {
    $contact = Contact::factory()->create();

    $rendered = Livewire::test(RecordDetailViewWidget::class, ['record' => $contact])->html();

    expect($rendered)->toContain('Record detail sidebar — Contact #' . $contact->id);
});

it('renders nothing inside the slot grid when the widget is mounted without a record', function () {
    $rendered = Livewire::test(RecordDetailViewWidget::class)->html();

    expect($rendered)->not->toContain('Record detail sidebar —');
});

it('the contact edit page redirects unauthenticated users', function () {
    $contact = Contact::factory()->create();

    $response = $this->get('/admin/contacts/' . $contact->id . '/edit');

    $response->assertRedirect();
});
