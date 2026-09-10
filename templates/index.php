<?php

script('careforms', 'forms/home-health-aide');
script('careforms', 'forms/nurses-progress-note');
script('careforms', 'form-renderer');
script('careforms', 'page');
script('careforms', 'patients');
script('careforms', 'reports');
script('careforms', 'audit');
script('careforms', 'form-admin');
script('careforms', 'review');
script('careforms', 'printing');
style('careforms', 'page');
style('careforms', 'nurses');
style('careforms', 'patients');
style('careforms', 'reports');
style('careforms', 'audit');
style('careforms', 'form-admin');
style('careforms', 'printing');

?>

<div id="app-content" class="careforms-app-content">
    <div id="careforms-app">
        <header class="careforms-header">
            <div>
                <h1>CareForms</h1>
                <p>Secure field data collection and reporting</p>
            </div>
        </header>

        <nav class="careforms-tabs" aria-label="CareForms">
            <button type="button" class="careforms-tab active" data-view="work">My Work</button>
            <button type="button" class="careforms-tab" data-view="forms">Forms</button>
            <button type="button" class="careforms-tab" data-view="review" hidden>Review Queue</button>
            <button type="button" class="careforms-tab" data-view="patients" hidden>Patients</button>
            <button type="button" class="careforms-tab" data-view="reports">Reports</button>
            <button type="button" class="careforms-tab" data-view="form-admin" hidden>Form Admin</button>
            <button type="button" class="careforms-tab" data-view="audit" hidden>Audit Log</button>
        </nav>

        <main id="careforms-content">
            <section class="careforms-view active" data-view-panel="work"><div id="careforms-work-browser"></div></section>
            <section class="careforms-view" data-view-panel="forms" hidden><div id="careforms-forms-browser"></div></section>
            <section class="careforms-view" data-view-panel="review" hidden><div id="careforms-review-browser"></div></section>
            <section class="careforms-view" data-view-panel="patients" hidden><div id="careforms-patients-browser"></div></section>
            <section class="careforms-view" data-view-panel="reports" hidden><div id="careforms-reports-browser"></div></section>
            <section class="careforms-view" data-view-panel="form-admin" hidden><div id="careforms-form-admin-browser"></div></section>
            <section class="careforms-view" data-view-panel="audit" hidden><div id="careforms-audit-browser"></div></section>
        </main>
    </div>
</div>