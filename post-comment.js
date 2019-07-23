const puppeteer = require('puppeteer');
const fs = require('fs');
const readline = require('readline');

var url = process.argv[2];
const rl = readline.createInterface({
  input: process.stdin,
  output: process.stdout
});


(async () => {
     const log_error = async function(page){
         console.log('snapshot to debug.png & debug.html');
         const {clientHeight, clientWidth} = await page.evaluate( () => {
             window.scrollTo( 0, 0 );
             return {clientHeight: document.body.clientHeight, clientWidth: document.body.clientWidth};
         });

         html = await page.$eval('html', e => e.outerHTML);
         await page.screenshot({path: 'debug.png', fullPage: true});

         fs.writeFileSync('debug.html', html);
         console.log('snapshot done');
     };
     const browser = await puppeteer.launch({
        headless: true,
        args: ["--window-size=2400,1239", "--no-sandbox"]
    });
     const page = await browser.newPage();

     await page.goto(url);
     var start = (new Date).getTime();
     await page.evaluate(() => {
        require("IntlUtils").setCookieLocale("en_US", "zh_TW", document.location, "www_card_selector", 0); return false;
     });
     while (true) {
         try {
             await page.$eval('#u_0_c', e => e.outerHTML);
             break;
         } catch (e) {
            if ((new Date).getTime() - start > 10000) {
                var answer = rl.question("超過 10 秒沒有找到 #u_0_c , 你確定要繼續嗎？可能遇到驗證碼");
            }
         }
    }

    console.log("移除登入框...");
    await page.evaluate(() => {
        document.querySelector('#u_0_c').remove();
    });

    console.log("點開留言");
    // click a[data-testid="UFI2CommentsCount/root"]
    while (true) {
        const { click } = await page.evaluate(() => {
            var a_doms = document.querySelectorAll('#stream_pagelet a');
            for (var i = 0; i < a_doms.length; i ++){
                if (a_doms[i].getAttribute('data-testid') == 'UFI2CommentsCount/root') {
                    a_doms[i].click();
                    return {click: true};
                }
            }
            return {click: false};
        });
        if (click) {
            break;
        }
    }

    console.log("切換全部留言");
    // click a[data-testid="UFI2ViewOptionsSelector/link"]
    while (true) {
        const { click } = await page.evaluate(() => {
            var a_doms = document.querySelectorAll('#stream_pagelet a');
            for (var i = 0; i < a_doms.length; i ++){
                if (a_doms[i].getAttribute('data-testid') == 'UFI2ViewOptionsSelector/link') {
                    a_doms[i].click();
                    return {click: true};
                }
            }
            return {click: false};
        });
        if (click) {
            break;
        }
    }

    console.log("切換全部留言");
    // document.querySelector('a[role="menuitemcheckbox"]')
    while (true) {
        const { click } = await page.evaluate(() => {
            var a_doms = document.querySelectorAll('a[role="menuitemcheckbox"]');
            for (var i = 0; i < a_doms.length; i ++){
                if (a_doms[i].innerText.match(/All comments/)) {
                    a_doms[i].click();
                    return {click: true};
                }
            }
            return {click: false};
        });
        if (click) {
            break;
        }
    }

    var start = (new Date).getTime();

    console.log("開始點 see more");
    while (true) {
        if ((new Date).getTime() - start > 20000) {
            console.log("檢查 20 秒，中止...");
            break;
        }

        const { hit } = await page.evaluate( () => {
            var a_doms = document.querySelectorAll('#stream_pagelet a');
            var hit = 0;
            for (var i = 0; i < a_doms.length; i ++){
                if (a_doms[i].getAttribute('data-testid') == 'UFI2CommentsPagerRenderer/pager_depth_1') {
                    a_doms[i].click();
                    hit ++;
                }
                if (a_doms[i].getAttribute('data-testid') == 'UFI2CommentsPagerRenderer/pager_depth_0') {
                    a_doms[i].click();
                    hit ++;
                }
                if (a_doms[i].getAttribute('role') == 'button' && a_doms[i].innerText == 'See More') {
                    a_doms[i].click();
                    hit ++;
                }
            }
            var span_doms = document.querySelectorAll('span[role="progressbar"]');
            hit += span_doms.length;
            return {
                hit: hit,
            };
        });
        console.log("hit=" + hit);
        if (!hit) {
            console.log('沒按鈕可按了');
            break;
        }
    }

    await log_error(page);
//    await browser.close();
})();

