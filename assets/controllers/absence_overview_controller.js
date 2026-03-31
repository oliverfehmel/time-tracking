import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["tooltip"];

    connect() {
        this.activeEl = null;
    }

    showTooltip(event) {
        const el = event.currentTarget;
        const html = el.getAttribute("data-absence-tooltip");
        if (!html) return;

        this.activeEl = el;
        this.tooltipTarget.innerHTML = html;
        this.tooltipTarget.hidden = false;

        this.position(event);
    }

    moveTooltip(event) {
        if (!this.activeEl) return;
        this.position(event);
    }

    hideTooltip() {
        this.activeEl = null;
        if (this.hasTooltipTarget) {
            this.tooltipTarget.hidden = true;
        }
    }

    position(event) {
        const tooltip = this.tooltipTarget;
        const pad = 14;

        // first set roughly, then clamp
        let left = event.clientX + pad;
        let top  = event.clientY + pad;

        tooltip.style.left = left + "px";
        tooltip.style.top = top + "px";

        const rect = tooltip.getBoundingClientRect();

        if (left + rect.width > window.innerWidth - 8) {
            left = event.clientX - rect.width - pad;
        }
        if (top + rect.height > window.innerHeight - 8) {
            top = event.clientY - rect.height - pad;
        }

        tooltip.style.left = left + "px";
        tooltip.style.top = top + "px";
    }
}
