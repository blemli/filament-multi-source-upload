<div
    data-msu-tabs
    class="fi-msu"
    x-data="{
        tab: 'file',

        // The source switch sits in the field's label row, outside this
        // scope: it announces its choice as a `msu-tab` event keyed by the
        // field, and hears back when the panes change tab on their own
        // (an import lands on the file pane).
        init() {
            this.$watch('tab', (tab) => this.$dispatch('msu-tab', { key: @js($key), tab }))
        },

        followSwitch(event) {
            if (event.detail?.key !== @js($key) || event.detail?.tab === this.tab) {
                return
            }

            this.tab = event.detail.tab
            this.error = null
        },
        importing: false,
        url: '',
        error: null,

        // A link — or an image — dragged in from another browser tab arrives
        // as text/uri-list. That URL is worth more than the File the browser
        // may attach alongside it: the dragged File is named after the URL's
        // last segment (often extension-less, e.g. «image») and typed after
        // whatever the page served, so FilePond's type check refuses it;
        // the server-side import sniffs the real type from the bytes and
        // names the file properly. Local files carry no URL and stay
        // FilePond's business.
        droppedUrl(event) {
            const types = Array.from(event.dataTransfer?.types ?? [])

            if (! types.includes('text/uri-list')) {
                return null
            }

            const url = (event.dataTransfer.getData('text/uri-list') || '')
                .split(/\r?\n/)
                .map((line) => line.trim())
                .find((line) => line !== '' && ! line.startsWith('#')) ?? ''

            return /^https?:\/\//i.test(url) ? url : null
        },

        async importDrop(event) {
            const url = this.droppedUrl(event)

            if (url === null) {
                return
            }

            event.preventDefault()
            event.stopPropagation()

            this.url = url
            await this.importFromUrl()

            // A failed drop must not fail silently: the file pane is showing,
            // the error lives on the URL pane — bring that pane forward so the
            // link and the reason are visible and can be retried or edited.
            if (this.error) {
                this.tab = 'url'
            }
        },

        // FilePond is created once Filament's upload component has fetched
        // its files — a drop within the first moments of the page would
        // find no pond yet. Wait briefly instead of failing the import.
        async awaitPond() {
            for (let attempt = 0; attempt < 50; attempt++) {
                const uploadEl = this.$refs.filePane.querySelector('[wire\\:ignore]')
                const fileUpload = uploadEl ? Alpine.$data(uploadEl) : null

                if (fileUpload?.pond) {
                    return fileUpload
                }

                await new Promise((resolve) => setTimeout(resolve, 50))
            }

            return null
        },

        // FilePond skips its layout pass while its root is display:none and
        // drops the «file added» action with it — the drop label then stays
        // on top of the item and covers its remove button (Firefox shows the
        // pane a frame later than Chrome). Hand the file over only once the
        // file pane is actually laid out.
        async awaitVisiblePane() {
            await this.$nextTick()

            for (let attempt = 0; attempt < 20; attempt++) {
                if (this.$refs.filePane.offsetParent !== null) {
                    break
                }

                await new Promise((resolve) => requestAnimationFrame(resolve))
            }

            await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)))
        },

        async importFromUrl() {
            const url = this.url.trim()

            if (url === '' || this.importing) {
                return
            }

            this.importing = true
            this.error = null

            let handedToFilePond = false

            try {
                // Fetch the bytes server-side (SSRF/size/timeout guarded) and
                // receive them as a data URL, then rebuild a real File so it can
                // go through FilePond's normal pipeline — no duplicated logic.
                const result = await $wire.callSchemaComponentMethod(@js($key), 'fetchRemoteFile', { url })

                if (! result || result.error) {
                    this.error = result?.error ?? @js($genericErrorMessage)

                    return
                }

                const blob = await (await fetch(result.dataUrl)).blob()
                const file = new File([blob], result.name, { type: result.type ?? blob.type })

                const fileUpload = await this.awaitPond()

                if (! fileUpload) {
                    this.error = @js($genericErrorMessage)

                    return
                }

                // Show the upload pane, then let FilePond validate (type/size),
                // preview, upload and manage the file exactly like a local one.
                // Any validation failure surfaces inline on the FilePond item.
                this.tab = 'file'
                await this.awaitVisiblePane()
                handedToFilePond = true

                await fileUpload.pond.addFile(file)

                this.url = ''
            } catch (error) {
                // Once the file is in FilePond, FilePond shows why it was
                // refused on the item itself; a message here would only wait,
                // stale, on the URL pane — even if the user has switched back
                // to it while FilePond was still deciding.
                if (! handedToFilePond) {
                    this.error = this.error ?? @js($genericErrorMessage)
                }
            } finally {
                this.importing = false
            }
        },
    }"
    x-on:msu-tab.window="followSwitch($event)"
    x-on:dragover="if (Array.from($event.dataTransfer?.types ?? []).includes('text/uri-list')) $event.preventDefault()"
    x-on:drop.capture="importDrop($event)"
>
    <div x-ref="filePane" x-show="tab === 'file'">
        {!! $filePane !!}
    </div>

    <div x-show="tab === 'url'" x-cloak class="fi-msu-url">
        <div class="fi-msu-url-row">
            <x-filament::input.wrapper class="fi-msu-url-field">
                <x-filament::input
                    type="url"
                    x-model="url"
                    x-on:input="error = null"
                    x-bind:disabled="importing"
                    :placeholder="$urlPlaceholder"
                    x-on:keydown.enter.prevent="importFromUrl()"
                />
            </x-filament::input.wrapper>

            <x-filament::button
                x-bind:disabled="importing || url.trim() === ''"
                x-on:click="importFromUrl()"
            >
                <span x-show="! importing">{{ $importLabel }}</span>
                <span x-show="importing" x-cloak>…</span>
            </x-filament::button>
        </div>

        <p x-show="error" x-text="error" x-cloak class="fi-msu-error"></p>
    </div>
</div>
