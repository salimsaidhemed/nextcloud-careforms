<?php

script('careforms', 'forms/home-health-aide');
script('careforms', 'forms/nurses-progress-note');
script('careforms', 'form-renderer');
script('careforms', 'page');
style('careforms', 'page');
style('careforms', 'nurses');

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
            <button type="button" class="careforms-tab" data-view="reports">Reports</button>
        </nav>

        <main id="careforms-content">
            <section class="careforms-view active" data-view-panel="work"><div id="careforms-work-browser"></div></section>
            <section class="careforms-view" data-view-panel="forms" hidden><div id="careforms-forms-browser"></div></section>
            <section class="careforms-view" data-view-panel="reports" hidden>
                <h2>Reports</h2>
                <div class="careforms-empty-state"><h3>Reports are not enabled yet</h3><p>Reporting will be added after the form and submission workflows are in place.</p></div>
            </section>
        </main>
    </div>
</div>