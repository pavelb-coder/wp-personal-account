/**
 * Mobile integration test against a disposable WordPress installation.
 * Requires playwright-core (NODE_PATH may point to its installation) and Chromium.
 * Configure WPPA_TEST_BASE_URL, WPPA_TEST_PAGE_ID, WPPA_TEST_MENTOR_ID,
 * WPPA_TEST_MENTOR_LOGIN, WPPA_TEST_CURATOR_LOGIN, WPPA_TEST_MEMBER_LOGIN,
 * WPPA_TEST_PASSWORD. Optional WPPA_TEST_SECTION_ID tests a standalone chat block;
 * WPPA_TEST_LOGIN_BLOCK_ID, WPPA_TEST_REGISTER_BLOCK_ID and WPPA_TEST_AUTH_BLOCK_ID test guest blocks.
 * WPPA_TEST_WRITE=1 sends a test message: only use on a disposable site.
 */
'use strict';
const { chromium } = require('playwright-core');
const env = process.env;
const required = ['WPPA_TEST_BASE_URL','WPPA_TEST_PAGE_ID','WPPA_TEST_MENTOR_ID','WPPA_TEST_MENTOR_LOGIN','WPPA_TEST_CURATOR_LOGIN','WPPA_TEST_MEMBER_LOGIN','WPPA_TEST_PASSWORD'];
for (const k of required) if (!env[k]) throw new Error('Missing ' + k);
const base = env.WPPA_TEST_BASE_URL.replace(/\/$/, '');
const id = Number(env.WPPA_TEST_PAGE_ID), mentor = Number(env.WPPA_TEST_MENTOR_ID);
const account = base + '/?page_id=' + id;
const widthCases = [320,375,768,1280];
const roleCases = [
  { role:'mentor', login:env.WPPA_TEST_MENTOR_LOGIN, tabs:['dashboard','profile','team','messages','security'] },
  { role:'curator', login:env.WPPA_TEST_CURATOR_LOGIN, tabs:['team','files','messages','participants','mailing','security'] },
  { role:'member', login:env.WPPA_TEST_MEMBER_LOGIN, tabs:['dashboard','profile','team','security'] }
];
let count = 0;
function check(condition, name) { if (!condition) throw new Error('FAIL ' + name); ++count; console.log('OK   ' + name); }
async function login(page, name) {
  await page.goto(base + '/wp-login.php');
  await page.locator('#user_login').fill(name);
  await page.locator('#user_pass').fill(env.WPPA_TEST_PASSWORD);
  await page.locator('#wp-submit').click();
  await page.waitForURL(u => !u.pathname.endsWith('/wp-login.php'));
}
function tabUrl(tab, role) { return account + '&wppa_tab=' + tab + (tab === 'messages' && role === 'curator' ? '&wppa_chat_mentor=' + mentor : ''); }
async function metrics(page) {
  return page.evaluate(() => {
    const root = document.querySelector('.wppa');
    const nav = root && root.querySelector('.wppa-nav');
    const links = nav ? [...nav.querySelectorAll('.wppa-nav__link')].map(e => ({height:e.getBoundingClientRect().height, width:e.getBoundingClientRect().width})) : [];
    const fields = root ? [...root.querySelectorAll('input:not([type=hidden]):not([type=checkbox]):not([type=radio]),textarea,select')].filter(e=>e.getBoundingClientRect().width && e.getBoundingClientRect().height).map(e=>({font:parseFloat(getComputedStyle(e).fontSize), width:e.getBoundingClientRect().width, right:e.getBoundingClientRect().right})) : [];
    return { viewport:innerWidth, scroll:document.documentElement.scrollWidth, root:root && root.getBoundingClientRect().width, nav:nav && getComputedStyle(nav).display, links, fields };
  });
}
(async () => {
  const browser = await chromium.launch({ executablePath:env.CHROMIUM_PATH || '/usr/bin/chromium', args:['--no-sandbox'] });
  try {
    for (const width of widthCases) {
      for (const role of roleCases) {
        const context = await browser.newContext({ viewport:{width,height:800}, isMobile:width < 600, hasTouch:width < 600, deviceScaleFactor:1 });
        const page = await context.newPage(); const errors = [];
        page.on('pageerror', error => { if (!page.url().includes('/wp-admin/')) errors.push(page.url() + ': ' + (error.stack || error.message)); });
        await login(page, role.login);
        for (const tab of role.tabs) {
          await page.goto(tabUrl(tab, role.role));
          await page.waitForSelector('.wppa');
          const m = await metrics(page);
          check(m.root > 0 && m.viewport <= width + 1 && m.scroll <= width + 1, `${width}px ${role.role}/${tab}: без горизонтального переполнения страницы`);
          check(width > 640 ? m.nav === 'flex' : (m.nav === 'grid' && m.links.every(x => x.height >= 43)), `${width}px ${role.role}/${tab}: навигация соответствует размеру экрана`);
          check(m.fields.every(x => (width > 640 || x.font >= 15.9) && x.width <= m.root + 1 && x.right <= width + 1), `${width}px ${role.role}/${tab}: поля помещаются, на телефоне шрифт не менее 16px`);
          if (tab === 'messages' && role.role !== 'member') {
            check(await page.locator('[data-wppa-chat-history]').count() === 1, `${width}px ${role.role}: чат открыт`);
            if (width <= 640) check((await page.locator('.wppa-chat__form textarea').boundingBox()).height >= 95, `${width}px ${role.role}: поле чата удобно для ввода`);
          }
        }
        // Длинные имена, адреса и ссылки не расширяют страницу.
        await page.goto(tabUrl(role.role === 'member' ? 'team' : 'messages', role.role));
        await page.evaluate(() => {
          const n = document.querySelector('.wppa-header__name'), e = document.querySelector('.wppa-header__email'), a = document.querySelector('.wppa-chat__unread a');
          if (n) n.textContent = 'ОченьдлинноеИмя'.repeat(8);
          if (e) e.textContent = 'longaddress'.repeat(10) + '@example.test';
          if (a) a.textContent = 'longlink'.repeat(20) + '@example.test';
        });
        if (width <= 640) { const m = await metrics(page); check(m.viewport <= width + 1 && m.scroll <= width + 1, `${width}px ${role.role}: длинные имена и ссылки не ломают экран`); }
        if (width === 320 && role.role === 'curator') {
          const badge = await page.evaluate(() => {
            const badge = document.querySelector('.wppa-chat__badge'); if (!badge) return null;
            badge.hidden = false; badge.textContent = '99999';
            const link = badge.closest('a'), a = link.getBoundingClientRect(), b = badge.getBoundingClientRect();
            return { fits: b.width + 1 >= badge.scrollWidth && b.left >= a.left - 1 && b.right <= a.right + 1, width:b.width, inner:badge.scrollWidth, left:b.left, right:b.right, parentLeft:a.left, parentRight:a.right, scroll:document.documentElement.scrollWidth };
          });
          check(badge && badge.fits && badge.scroll <= 321, '320px: большой счётчик сообщений не обрезается '+JSON.stringify(badge));
        }
        if (width === 320 && role.role === 'mentor' && env.WPPA_TEST_SECTION_ID) {
          await page.goto(base + '/?page_id=' + Number(env.WPPA_TEST_SECTION_ID));
          const m = await metrics(page);
          check(m.root > 0 && m.scroll <= width + 1 && await page.locator('[data-wppa-chat-history]').count() === 1, '320px: отдельный Gutenberg-блок чата доступен и помещается');
        }
        check(errors.length === 0, `${width}px ${role.role}: нет ошибок JavaScript: ${errors.join("; ")}`);
        await context.close();
      }
    }
    // Гостевые формы и существующие Gutenberg-блоки не вылезают за границы.
    for (const width of [320,375]) {
      const guest = await browser.newContext({ viewport:{width,height:800}, isMobile:true, hasTouch:true });
      const p = await guest.newPage();
      const pages = [account, account + '&wppa_view=register'];
      for (const k of ['WPPA_TEST_LOGIN_BLOCK_ID','WPPA_TEST_REGISTER_BLOCK_ID','WPPA_TEST_AUTH_BLOCK_ID']) if (env[k]) pages.push(base + '/?page_id=' + Number(env[k]));
      for (const url of pages) {
        await p.goto(url); await p.waitForSelector('.wppa'); const m = await metrics(p);
        check(m.scroll <= width + 1 && m.viewport <= width + 1 && m.fields.length > 0 && m.fields.every(x => x.font >= 15.9 && x.right <= width + 1), `${width}px гость: формы входа и регистрации удобны на телефоне`);
      }
      await guest.close();
    }
    // На таблицах куратора длинные данные не должны растягивать всю страницу.
    const tableContext = await browser.newContext({ viewport:{width:320,height:800}, isMobile:true, hasTouch:true });
    const tablePage = await tableContext.newPage(); await login(tablePage, env.WPPA_TEST_CURATOR_LOGIN); await tablePage.goto(tabUrl('team','curator'));
    const tableResult = await tablePage.evaluate(() => {
      const cell = document.querySelector('.wppa-table td'); if (!cell) return null;
      cell.textContent = 'Длинноеназваниекоманды'.repeat(12);
      return { display:getComputedStyle(cell).display, scroll:document.documentElement.scrollWidth, viewport:innerWidth };
    });
    check(tableResult && tableResult.display === 'block' && tableResult.scroll <= 321 && tableResult.viewport <= 321, '320px: таблица превращается в карточки, длинные данные помещаются');
    await tableContext.close();
    // Навигация и форма чата должны оставаться доступными при отключённом JS.
    const nojs = await browser.newContext({ viewport:{width:320,height:800}, isMobile:true, hasTouch:true, javaScriptEnabled:false });
    const p = await nojs.newPage(); await login(p, env.WPPA_TEST_CURATOR_LOGIN); await p.goto(tabUrl('messages','curator'));
    check(await p.locator('[data-wppa-chat-form] textarea').isVisible() && await p.locator('.wppa-nav__link').count() > 0, '320px: чат и навигация доступны без JavaScript');
    const details = p.locator('.wppa-chat__clear'); await details.locator('summary').click();
    check(await details.locator('button[type=submit]').isVisible(), '320px: подтверждение очистки доступно без JavaScript');
    await nojs.close();
    if (env.WPPA_TEST_WRITE === '1') {
      const context = await browser.newContext({ viewport:{width:320,height:800}, isMobile:true, hasTouch:true });
      const page = await context.newPage(); await login(page, env.WPPA_TEST_MENTOR_LOGIN); await page.goto(tabUrl('team','mentor'));
      await page.locator('.wppa-nav__link[href*="wppa_tab=messages"]').click();
      await page.waitForSelector('[data-wppa-chat-form]');
      check(page.url().includes('wppa_tab=messages'), '320px: переход в чат касанием вкладки работает');
      const value = 'Мобильная проверка ' + Date.now();
      await page.locator('[data-wppa-chat-form] textarea').fill(value); await page.locator('[data-wppa-chat-form] button[type=submit]').click();
      await page.locator('[data-wppa-chat-history]').getByText(value).waitFor({timeout:15000});
      check(await page.locator('[data-wppa-chat-form] textarea').inputValue() === '', '320px: отправка сообщения работает на телефоне');
      await context.close();
    }
    console.log(`Мобильная интеграция: ${count} OK / 0 FAIL`);
  } finally { await browser.close(); }
})().catch(error => { console.error(error.stack); process.exit(1); });
