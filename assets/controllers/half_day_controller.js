import { Controller } from "@hotwired/stimulus";

/*
 * Abwesenheitsantrag: Ein halber Tag ist nur für einen einzelnen Tag möglich.
 * Ist "Halber Tag" angehakt, wird das Enddatum auf das Startdatum gesetzt und gesperrt.
 * Die eigentliche Prüfung erfolgt serverseitig; das hier ist nur Komfort.
 */
export default class extends Controller {
    static targets = ["toggle", "start", "end"];

    connect() {
        this.sync();
    }

    sync() {
        if (!this.hasToggleTarget || !this.hasStartTarget || !this.hasEndTarget) {
            return;
        }

        const halfDay = this.toggleTarget.checked;
        if (halfDay && this.startTarget.value) {
            this.endTarget.value = this.startTarget.value;
        }
        // readonly statt disabled, damit der Wert weiterhin mitgesendet wird
        this.endTarget.readOnly = halfDay;
    }
}
