import { createApp } from 'vue';
import ObservationCockpit from './components/ObservationCockpit.vue';

const cockpit = document.getElementById('olo-observation-cockpit');

if (cockpit) {
    createApp(ObservationCockpit, {
        organsUrl: cockpit.dataset.organsUrl,
        activityUrl: cockpit.dataset.activityUrl,
    }).mount(cockpit);
}
