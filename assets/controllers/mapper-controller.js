import {Controller} from '@hotwired/stimulus';
import {average} from 'color.js'
import 'jquery'
import {RagnaBeat} from "../js/ragna-beat/ragnabeat";

import 'select2/dist/js/select2.full.min';

require('../../public/bundles/tetranzselect2entity/js/select2entity');
import '../js/plugins/modal_ajax';
require('../js/base');
require('../js/plugins/modal_ajax');
require('../js/plugins/ajax_link');
require('../js/plugins/rating');

export default class extends Controller {
    static targets = ['img', 'background']
    ragna = null;

    connect() {
    }

    disconnect() {
    }

    back() {
        // history.back();// Swup instance
        //  return false;
    }
}