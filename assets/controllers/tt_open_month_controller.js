import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static values = {
        openMonth: Number,
    }

    static targets = ['month']

    connect() {
        const m = this.openMonthValue
        if (!m || Number.isNaN(m)) return

        const el = this.monthTargets.find((node) => Number(node.dataset.month) === m)
        if (!el) return

        // Falls serverseitig nicht geöffnet wurde (oder Browser zickt): öffnen
        el.open = true

        // Scrollen + optional Fokus auf summary (damit man "ankommt")
        window.requestAnimationFrame(() => {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' })
            const summary = el.querySelector('summary')
            if (summary) summary.focus({ preventScroll: true })
        })
    }
}
