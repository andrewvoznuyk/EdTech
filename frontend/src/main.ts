import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import { weblatePlugin, initWeblate } from './weblate';

const app = createApp(App);

app.use(createPinia());
app.use(router);
app.use(weblatePlugin);

initWeblate();

app.mount('#app');
