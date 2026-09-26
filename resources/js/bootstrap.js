import _ from 'lodash';
import axios from 'axios';
import './echo';

window._ = _;
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
