import './bootstrap'
import { createInertiaApp } from '@inertiajs/react'
import { createRoot } from 'react-dom/client'
import { route } from 'ziggy-js'
import i18n from '../../Modules/Builder/resources/js/i18n'

createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob([
      './Pages/**/*.jsx',
      // nwidart v12 lowercases the module's `resources/` dir (Builder);
      // keep the capital `Resources/` pattern too for any module still
      // on the pre-v12 layout.
      '../../Modules/**/resources/js/Pages/**/*.jsx',
      '../../Modules/**/Resources/js/Pages/**/*.jsx',
    ], { eager: true })

    // HMVC style: Module::Page
    if (name.includes('::')) {
      const [module, page] = name.split('::')

      return pages[`../../Modules/${module}/resources/js/Pages/${page}.jsx`]
        ?? pages[`../../Modules/${module}/Resources/js/Pages/${page}.jsx`]
    }

    return pages[`./${name}.jsx`]
  },

  setup({ el, App, props }) {
    window.route = (name, params, absolute) => route(name, params, absolute, window.Ziggy)

    // Sync react-i18next UI language to the server's chosen locale. i18n inits
    // with lng:'en', and the language switcher changes the locale via a full
    // reload (/lang/{code}) — so on each load we point i18next at the shared
    // `currentLocale` prop, otherwise the UI text stays English on Arabic/etc.
    const locale = props?.initialPage?.props?.currentLocale
    if (locale && i18n.language !== locale) {
      i18n.changeLanguage(locale)
    }

    // Point the browser-tab favicon at the store logo. Both the storefront and
    // builder middleware share it as `logo`; the root blade ships no
    // <link rel="icon">, so without this the tab falls back to the browser
    // default globe on both the storefront and the builder setup pages.
    const logoUrl = props?.initialPage?.props?.logo
    if (logoUrl) {
      let iconLink = document.querySelector("link[rel~='icon']")
      if (!iconLink) {
        iconLink = document.createElement('link')
        iconLink.rel = 'icon'
        document.head.appendChild(iconLink)
      }
      iconLink.href = logoUrl
    }

    createRoot(el).render(<App {...props} />)
  },
})
