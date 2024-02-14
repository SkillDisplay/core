define(["require", "exports", "jquery", 'moment', 'TYPO3/CMS/Backend/Storage/Persistent', 'flatpickr/flatpickr.min'], function (require, exports, $, moment, PersistentStorage, flatpickr) {
  "use strict";

  class ReportingBackend {
    constructor() {
      $(() => this.domReady());
    }

    domReady() {
      require(['flatpickr/locales'], () => {
        let userLocale = PersistentStorage.get('lang');
        if (userLocale === '') {
          userLocale = 'default';
        }

        // link upper and lower date limits together
        let $lowerPicker = $('.js-datetimepicker-lower');
        let $upperPicker = $('.js-datetimepicker-upper');
        if ($lowerPicker.length > 0 && $upperPicker.length > 0) {
          let startPicker = flatpickr($lowerPicker.first(), {
            allowInput: true,
            locale: userLocale,
            onChange: function (selectedDates, dateStr, instance) {
              endPicker.set('minDate', selectedDates[0]);
            }
          });
          let endPicker = flatpickr($upperPicker.first(), {
            allowInput: true,
            locale: userLocale,
            onChange: function (selectedDates, dateStr, instance) {
              startPicker.set('maxDate', selectedDates[0]);
            }
          });

          endPicker.set('minDate', startPicker.selectedDates[0]);
        } else {
          flatpickr($('.js-datetimepicker'), {allowInput: true, locale: userLocale});
        }
      });
    }
  }

  return new ReportingBackend();
});
