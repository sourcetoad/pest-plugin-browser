<?php

declare(strict_types=1);

it('opens a new tab without affecting the original page', function (): void {
    Route::get('/page-a', fn (): string => 'page 1');
    Route::get('/page-b', fn (): string => 'page 2');

    $first = visit('/page-a');
    $first->assertSee('page 1');

    $second = $first->newTab('/page-b');
    $second->assertSee('page 2');

    $first->assertSee('page 1');
});

it('shares cookies between tabs in the same context', function (): void {
    Route::get('/origin', fn (): string => 'origin');

    $first = visit('/origin');
    $first->script("document.cookie = 'shared=yes; path=/'");

    $second = $first->newTab('/origin');
    $value = $second->script('document.cookie');

    expect($value)->toContain('shared=yes');
});

it('returns an awaitable webpage that supports chained assertions', function (): void {
    Route::get('/page-a', fn (): string => 'page 1');
    Route::get('/page-b', fn (): string => '<h1>page 2</h1>');

    $page = visit('/page-a')
        ->newTab('/page-b')
        ->assertSee('page 2');

    $page->assertSee('page 2');
});
