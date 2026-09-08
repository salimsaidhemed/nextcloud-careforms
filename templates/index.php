<?php

script('careforms', 'page');
style('careforms', 'page');

?>

<div id="careforms-app">

    <header class="careforms-header">
        <div>
            <h1>CareForms</h1>
            <p>Secure field data collection and reporting</p>
        </div>
    </header>

    <nav class="careforms-tabs" aria-label="CareForms">
        <button
            type="button"
            class="careforms-tab active"
            data-view="work">
            My Work
        </button>

        <button
            type="button"
            class="careforms-tab"
            data-view="forms">
            Forms
        </button>

        <button
            type="button"
            class="careforms-tab"
            data-view="reports">
            Reports
        </button>
    </nav>

    <main id="careforms-content">

        <section id="careforms-work-view">
            <h2>My Work</h2>

            <p>
                Drafts, returned forms and recent submissions
                will appear here.
            </p>
        </section>

    </main>

</div>