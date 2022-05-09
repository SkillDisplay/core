/// <amd-dependency path="twbs/bootstrap-datetimepicker" />

import * as $ from "jquery";

class ReportingBackend {

  constructor() {
    $(() => this.domReady());
  }

  domReady() {
    $('.js-datetimepicker').each(function (this: HTMLElement) {
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
      // cast to any as datetimepicker has no type information
      ($(this) as any).datetimepicker({format: format, useCurrent: false, icons: icons});
    });

    // link upper and lower date limits together
    let $lowerPicker = $('.js-datetimepicker-lower');
    let $upperPicker = $('.js-datetimepicker-upper');
    if ($lowerPicker.length > 0 && $upperPicker.length > 0) {
      $upperPicker.on("dp.change", function (e: any) {
        $lowerPicker.data("DateTimePicker").maxDate(e.date);
      });
      $lowerPicker.on("dp.change", function (e: any) {
        $upperPicker.data("DateTimePicker").minDate(e.date);
      });
    }
  }
}

export = new ReportingBackend();
