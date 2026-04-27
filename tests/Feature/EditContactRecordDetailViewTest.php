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
    (new \Database\Seeders\RecordDetailViewSeeder())->run();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
});

it('declares RecordDetailViewWidget on EditContact::getFooterWidgets()', function () {
    $page = new EditContact();
    $method = (new ReflectionClass($page))->getMethod('getFooterWidgets');
    $method->setAccessible(true);

    expect($method->invoke($page))->toBe([RecordDetailViewWidget::class]);
});

it('renders the Recent Notes heading when the Filament widget is mounted with a Contact record', function () {
    $contact = Contact::factory()->create();

    $rendered = Livewire::actingAs($this->admin)
        ->test(RecordDetailViewWidget::class, ['record' => $contact])->html();

    expect($rendered)->toContain('Recent Notes');
});

it('renders nothing inside the slot grid when the widget is mounted without a record', function () {
    $rendered = Livewire::actingAs($this->admin)
        ->test(RecordDetailViewWidget::class)->html();

    expect($rendered)->not->toContain('Recent Notes');
});

it('the contact edit page redirects unauthenticated users', function () {
    $contact = Contact::factory()->create();

    $response = $this->get('/admin/contacts/' . $contact->id . '/edit');

    $response->assertRedirect();
});
