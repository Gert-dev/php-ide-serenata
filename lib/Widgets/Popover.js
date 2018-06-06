/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS206: Consider reworking classes to avoid initClass
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let Popover;
const {Disposable} = require('atom');

module.exports =

//#*
// Widget that can be used to display information about a certain context.
//#
(Popover = (function() {
    Popover = class Popover extends Disposable {
        static initClass() {
            this.prototype.element = null;
        }

        /**
         * Constructor.
        */
        constructor() {
            super(() => { this.destructor(); });

            this.element = document.createElement('div');
            this.element.className = 'tooltip bottom fade php-ide-serenata-popover';
            this.element.innerHTML = '<div class="tooltip-arrow"></div><div class="tooltip-inner"></div>';

            document.body.appendChild(this.element);
        }

        /**
         * Destructor.
        */
        destructor() {
            this.hide();
            return document.body.removeChild(this.element);
        }

        /**
         * Retrieves the HTML element containing the popover.
         *
         * @return {HTMLElement}
        */
        getElement() {
            return this.element;
        }

        /**
         * sets the text to display.
         *
         * @param {String} text
        */
        setText(text) {
            const item = this.element.querySelector('.tooltip-inner');
            return item.innerHTML =
                `<div class="php-ide-serenata-popover-wrapper">${text.replace(/\n\n/g, '<br/><br/>')}</div>`;
        }

        /**
         * Shows a popover at the specified location with the specified text and fade in time.
         *
         * @param {Number} x The X coordinate to show the popover at (left).
         * @param {Number} y The Y coordinate to show the popover at (top).
        */
        show(x, y) {
            this.element.style.left = x + 'px';
            this.element.style.top = y + 'px';

            this.element.classList.add('in');
            return this.element.style.display = 'block';
        }

        /**
         * Hides the tooltip, if it is displayed.
        */
        hide() {
            this.element.classList.remove('in');
            return this.element.style.display = 'none';
        }
    };
    Popover.initClass();
    return Popover;
})());
