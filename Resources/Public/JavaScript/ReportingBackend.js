define(["require", "exports", "jquery", "twbs/bootstrap-datetimepicker"], function (require, exports, $) {
    "use strict";
    class ReportingBackend {
        constructor() {
            $(() => this.domReady());
        }
        domReady() {
            $('.js-datetimepicker').each(function () {
                let format = $(this).data('format');
                let icons = {
                    time: 'fa fa-clock-o',
                    date: 'fa fa-calendar',
                    up: 'fa fa-angle-up',
                    down: 'fa fa-angle-down',
                    previous: 'fa fa-angle-left',
                    next: 'fa fa-angle-right',
                    today: 'fa fa-crosshairs',
                    clear: 'fa fa-trash',
                    close: 'fa fa-remove'
                };
                $(this).datetimepicker({ format: format, useCurrent: false, icons: icons });
            });
            let $lowerPicker = $('.js-datetimepicker-lower');
            let $upperPicker = $('.js-datetimepicker-upper');
            if ($lowerPicker.length > 0 && $upperPicker.length > 0) {
                $upperPicker.on("dp.change", function (e) {
                    $lowerPicker.data("DateTimePicker").maxDate(e.date);
                });
                $lowerPicker.on("dp.change", function (e) {
                    $upperPicker.data("DateTimePicker").minDate(e.date);
                });
            }
        }
    }
    return new ReportingBackend();
});
