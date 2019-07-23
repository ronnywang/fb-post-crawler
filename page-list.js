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
        args: ["--window-size=2400,1239", "--no-sandbox", "--lang=en_US"]
    });
     const page = await browser.newPage();
     await page.goto(url);
     var start = (new Date).getTime();
     while (true) {
         try {
             await page.$eval('#pagelet_growth_expanding_cta', e => e.outerHTML);
             break;
         } catch (e) {
            if ((new Date).getTime() - start > 10000) {
                var answer = rl.question("超過 10 秒沒有找到 #pagelet_growth_expanding_cta, 你確定要繼續嗎？可能遇到驗證碼");
            }
         }
    }

    console.log("移除登入框...");
    await page.evaluate(() => {
        document.querySelector('#pagelet_growth_expanding_cta').remove();
    });

    // click a[data-comment-prelude-ref="action_link_bling"]
    var start = (new Date).getTime();
    var last_check = null;
    while (true) {
        if ((new Date).getTime() - start > 20000) {
            console.log("檢查 20 秒，中止...");
            break;
        }
        console.log('找尋 a.uiMorePagerPrimary 查看更多按鈕,並按下去');

        const {hit, height, box} = await page.evaluate( () => {
            var a_doms = document.querySelectorAll('a.uiMorePagerPrimary');
            var click = 0;
            var doms = document.querySelectorAll('._4-u2._4-u8');
            var box = doms.length;
            for (var i = 0; i < a_doms.length; i ++){
                window.scrollTo(0, a_doms[i].offsetTop);
                return {
                    hit: a_doms.length,
                    height: a_doms[i].offsetTop,
                    box: box,
                };
            }
            return {hit: 0, height: 0, box: box};
        });
        console.log("hit=" + hit + ',height=' + height + ',box=' + box);
        if (!hit) {
            console.log('找不到 a.uiMorePagerPrimary 按鈕，中斷');
            break;
        }
        if (box >= 50) {
            console.log("超過 50 筆，中止...");
            break;
        }
    }

    await log_error(page);
    await browser.close();
})();

