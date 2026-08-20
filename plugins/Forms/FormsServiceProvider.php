<?php

namespace Plugins\Forms;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Plugins\AdminContribution;
use App\Plugins\EmailTemplateRegistry;
use App\Plugins\PluginAdminRegistry;
use App\Services\WidgetRegistry;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Plugins\Forms\Observers\FormSubmissionObserver;
use Plugins\Forms\Policies\FormPolicy;
use Plugins\Forms\Policies\FormSubmissionPolicy;
use Plugins\Forms\Widgets\WebForm\WebFormDefinition;

class FormsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Admin socket (docs/plugin-contract.md surfaces 3/4): the Form
        // resource, its three pages, and the submissions relation manager are
        // discovered from the plugin. No permission names declared — the form
        // and form-submission permissions are part of core's PermissionSeeder
        // role matrix (the extracted-vertical nuance, surface 3). No
        // capability declared — nothing consumes forms presence; the presence
        // signal, were one ever needed, is route presence
        // (Route::has('forms.submit')).
        $this->app->make(PluginAdminRegistry::class)->register(new AdminContribution(
            plugin: 'forms',
            resourcesPath: __DIR__ . '/Filament/Resources',
            resourcesNamespace: 'Plugins\\Forms\\Filament\\Resources',
        ));

        // Email-template handles (surface 7): both form handles contributed
        // with defaults byte-identical to the seeder rows they replace — the
        // registry is now the single source of their defaults (this resolves
        // the portal_form_collision array/seeder disagreement recorded in the
        // housekeeping inbox: the handle never had a core-array entry, and
        // with the plugin absent neither handle exists).
        $templates = $this->app->make(EmailTemplateRegistry::class);
        $templates->contribute('form_submission', [
            'subject' => 'New submission: {{form_title}}',
            'body'    => '<p>A new submission was received from the <strong>{{form_title}}</strong> form.</p>{{submission}}',
        ]);
        $templates->contribute('portal_form_collision', [
            'subject' => 'Someone submitted a form using your email address',
            'body'    => '<p>Hi {{first_name}},</p><p>A form on our website was submitted using your email address. If this was you, please <a href="{{login_url}}">log in to your account</a> and complete the action while signed in.</p><p>If this was not you, no action is needed — the submission was not processed.</p>',
        ]);
    }

    public function boot(): void
    {
        View::addNamespace('plugin-forms-widgets', __DIR__ . '/Widgets');

        // The first plugin-owned Blade component (contract 0.21.0):
        // <x-public-form> resolves from the plugin's components directory, so
        // the WebForm widget template keeps rendering it byte-identically.
        // The unprefixed anonymous-component path keeps the tag name — and
        // the FormResource helper text that documents it — unchanged.
        Blade::anonymousComponentPath(__DIR__ . '/resources/views/components');

        $this->app->make(WidgetRegistry::class)->register(new WebFormDefinition());

        // The observer inversion (front-loaded decision 2): the #[ObservedBy]
        // attribute came off the core FormSubmission model — a core-model
        // reference to a plugin class would be a banned core→plugin reach —
        // and the plugin registers the observer here instead (the Memberships
        // Membership::observe() precedent on a core model).
        FormSubmission::observe(FormSubmissionObserver::class);

        Gate::policy(Form::class, FormPolicy::class);
        Gate::policy(FormSubmission::class, FormSubmissionPolicy::class);

        // The forms.submit route registers ahead of core's page-slug
        // catch-all — provider boot() runs before routes/web.php loads
        // (contract surface 4, front-of-house half).
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
    }
}
