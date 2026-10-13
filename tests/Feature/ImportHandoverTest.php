<?php

declare(strict_types=1);

/*
 * The URL / drop import hands the fetched file to FilePond through addFile().
 * Doing that in the same tick that shows the file pane left FilePond's drop
 * label on top of the item in Firefox: FilePond skips its layout pass while
 * the root is display:none and drops the «file added» action with it, so the
 * label never hid and covered the remove button. The pane must be laid out
 * first — and a drop in the first moments of a page must wait for the pond
 * instead of failing.
 */
it('hands the imported file over only once the file pane is laid out', function (): void {
    $view = file_get_contents(__DIR__ . '/../../resources/views/components/multi-source-file-upload.blade.php');

    expect($view)
        ->toContain('async awaitVisiblePane()')
        ->toContain('if (this.$refs.filePane.offsetParent !== null) {')
        ->toContain("this.tab = 'file'\n                await this.awaitVisiblePane()\n                handedToFilePond = true");
});

it('waits for FilePond on a drop that lands before the pond exists', function (): void {
    $view = file_get_contents(__DIR__ . '/../../resources/views/components/multi-source-file-upload.blade.php');

    expect($view)
        ->toContain('async awaitPond()')
        ->toContain('const fileUpload = await this.awaitPond()')
        ->not->toContain("if (! fileUpload || ! fileUpload.pond) {");
});
