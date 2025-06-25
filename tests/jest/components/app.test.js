const VueTestUtils = require('@vue/test-utils');
const AppComponent = require('../../../modules/ext.donationInterface.donorPortal/components/App.vue');
const { createRouter, createWebHashHistory } = require('vue-router');

describe('App Component', () => {
    let router = null;

    beforeEach(() => {
        router = createRouter({
            history: createWebHashHistory(),
            routes: [
                {
                    path: '/',
                    component: {
                        template: 'Welcome to the home screen <a href="/login">Go to login</a>'
                    }
                },
                {
                    path: '/login',
                    component: {
                        template: 'Welcome to the login screen'
                    }
                }
            ]
        })
    })

    it('Home screen renders successfully', async () => {
        router.push('/');

        await router.isReady();
        const wrapper = VueTestUtils.mount(AppComponent, {
            global: {
                plugins: [router]
            }
        });

        expect(wrapper.html()).toContain('Welcome to the home screen');
    });

    it('Login screen renders successfully', async () => {
        router.push('/login');

        await router.isReady();
        const wrapper = VueTestUtils.mount(AppComponent, {
            global: {
                plugins: [router]
            }
        });

        expect(wrapper.html()).toContain('Welcome to the login screen');
    });

});
