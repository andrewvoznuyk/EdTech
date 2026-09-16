import fs from 'fs';
import path from 'path';

const baseUrl = process.env.WEBLATE_URL || 'http://localhost:8088/api';
const token = process.env.WEBLATE_TOKEN || 'wlu_P317EetTKKjMKNNL6HWS0Rj7yn9755aTTBMw';
const project = process.env.WEBLATE_PROJECT || 'edtech';
const component = process.env.WEBLATE_COMPONENT || 'frontend';
const baseDir = path.resolve(process.cwd(), 'src/weblate/translations');

async function downloadTranslations() {
  const listUrl = `${baseUrl}/components/${project}/${component}/translations/`;
  try {
    const listRes = await fetch(listUrl, {
      headers: {
        Authorization: `Token ${token}`,
        Accept: 'application/json',
      },
    });

    if (!listRes.ok) {
      return;
    }

    const listData = await listRes.json();
    const items = listData.results || [];

    for (const item of items) {
      const lang = item.language_code;
      const fileUrl = item.file_url || `${baseUrl}/translations/${project}/${component}/${lang}/file/`;
      const fileRes = await fetch(fileUrl, {
        headers: {
          Authorization: `Token ${token}`,
          Accept: 'application/json',
        },
      });

      if (!fileRes.ok) {
        continue;
      }

      const translations = await fileRes.json();
      const targetJsonPath = path.join(baseDir, `${lang}.json`);
      fs.writeFileSync(targetJsonPath, JSON.stringify(translations, null, 2) + '\n', 'utf-8');
    }
  } catch {}
}

async function uploadTranslations() {
  const enJsonPath = path.join(baseDir, 'en.json');
  if (!fs.existsSync(enJsonPath)) {
    return;
  }

  const fileData = fs.readFileSync(enJsonPath);
  const boundary = '----WebKitFormBoundary' + Math.random().toString(36).substring(2);
  const pre = `--${boundary}\r\nContent-Disposition: form-data; name="file"; filename="en.json"\r\nContent-Type: application/json\r\n\r\n`;
  const post = `\r\n--${boundary}\r\nContent-Disposition: form-data; name="method"\r\n\r\nreplace\r\n--${boundary}--\r\n`;
  const payload = Buffer.concat([Buffer.from(pre, 'utf-8'), fileData, Buffer.from(post, 'utf-8')]);
  const url = `${baseUrl}/translations/${project}/${component}/en/file/`;

  try {
    await fetch(url, {
      method: 'POST',
      headers: {
        Authorization: `Token ${token}`,
        'Content-Type': `multipart/form-data; boundary=${boundary}`,
      },
      body: payload,
    });
  } catch {}
}

const action = process.argv[2];

if (action === 'pull') {
  await downloadTranslations();
} else if (action === 'push') {
  await uploadTranslations();
} else {
  await uploadTranslations();
  await downloadTranslations();
}
