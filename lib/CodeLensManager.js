/* global atom */

'use strict';

module.exports =

/**
 * Manages visual information around code lenses returned by the server.
 */
class CodeLensManager
{
    /**
     * Constructor.
     */
    constructor() {
        this.markers  = {};
        this.annotations = {};
    }

    /**
     * @param {TextEditor} editor
     * @param {Array}      codeLenses
     * @param {Callable}   executeCommandHandler
     */
    process(editor, codeLenses, executeCommandHandler) {
        this.removeMarkers(editor);

        const {Convert} = require('atom-languageclient');

        // You cannot have multiple markers on the exact same line, you need to combine them into one element and
        // have one marker, so do some grouping up front.
        const codeLensesGroupedByLine = codeLenses.reduce((accumulator, codeLens) => {
            const range = Convert.lsRangeToAtomRange(codeLens.range);

            if (!accumulator.has(range.start.row)) {
                accumulator.set(range.start.row, new Array());
            }

            accumulator.get(range.start.row).push(codeLens);

            return accumulator;
        }, new Map());

        codeLensesGroupedByLine.forEach((codeLenses, line) => {
            this.processForLine(editor, codeLenses, line, executeCommandHandler);
        });
    }

    /**
     * @param {TextEditor} editor
     * @param {Array}      codeLenses
     * @param {Number}     line
     * @param {Callable}   executeCommandHandler
     */
    processForLine(editor, codeLenses, line, executeCommandHandler) {
        const {Range, Point} = require('atom');
        const {Convert} = require('atom-languageclient');

        const marker = this.registerMarker(editor, new Range(new Point(line, 0), new Point(line, 1)), {
            invalidate : 'touch',
        });

        const codeLensLineElement = document.createElement('div');
        codeLensLineElement.classList.add('php-ide-serenata-code-lens-wrapper');

        let charactersTaken = 0;

        codeLenses.forEach((codeLens) => {
            if (!codeLens.command) {
                // To support this, one would have to send a resolve request and show some sort of placeholder
                // beforehand, as we wouldn't know what title to show yet.
                throw new Error('Code lenses with unresolved commands are currently not supported');
            }

            const range = Convert.lsRangeToAtomRange(codeLens.range);
            const paddingSpacesNeeded = range.start.column - charactersTaken;

            // Having one marker per line  (see above) means that we need to do padding ourselves when multiple code
            // lenses are present. This can happen in cases where multiple properties are on one line, and more than one
            // of them is an override. ot great, but it gets the job done.
            const paddingSpanElement = document.createElement('span');
            paddingSpanElement.innerHTML = '&nbsp;'.repeat(paddingSpacesNeeded);

            const anchorElement = document.createElement('a');
            anchorElement.innerHTML = codeLens.command.title;
            anchorElement.classList.add('badge');
            anchorElement.classList.add('badge-small');
            anchorElement.href = '#';
            anchorElement.addEventListener('click', () => {
                executeCommandHandler({
                    command: codeLens.command.command,
                    arguments: codeLens.command.arguments,
                });
            });

            charactersTaken += paddingSpacesNeeded + anchorElement.innerHTML.length ;

            const wrapperElement = document.createElement('div');
            wrapperElement.classList.add('php-ide-serenata-code-lens');
            // wrapperElement.style.marginLeft = range.start.column + 'em';
            wrapperElement.appendChild(paddingSpanElement);
            wrapperElement.appendChild(anchorElement);

            codeLensLineElement.appendChild(wrapperElement);
        });

        editor.decorateMarker(marker, {
            type: 'block',
            item: codeLensLineElement,
        });
    }

    /**
     * @param {TextEditor} editor
     * @param {Range}      range
     * @param {Object}     options
     *
     * @return {Object}
     */
    registerMarker(editor, range, options) {
        const marker = editor.markBufferRange(range, options);

        if (!(editor.id in this.markers)) {
            this.markers[editor.id] = [];
        }

        this.markers[editor.id].push(marker);

        return marker;
    }

    /**
     * @param {TextEditor} editor
     */
    removeMarkers(editor) {
        for (let i in this.markers[editor.id]) {
            const marker = this.markers[editor.id][i];
            marker.destroy();
        }

        this.markers[editor.id] = [];
    }
};
