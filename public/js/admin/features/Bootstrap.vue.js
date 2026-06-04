const { createApp } = Vue
const { createVuetify } = Vuetify;

import theme1 from '../../../css/vue/theme1.js';

const vuetify = createVuetify({
    theme: {
        defaultTheme: 'theme1', 
        themes: {
            theme1
        }
    }
});

import App from './App.vue.js';

axios.defaults.baseURL = bootstrap.baseURL;

const app = createApp(App)
app.use(vuetify)
app.provide('bootstrap', bootstrap);
app.mount('#app')
