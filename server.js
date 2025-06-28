const express = require('express');
const cors = require('cors');
const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');
const { exec } = require('child_process');
const util = require('util');
const execAsync = util.promisify(exec);

const app = express();
const PORT = process.env.PORT || 3000;
app.use(cors());
app.use(express.json());

function detectPlatform(url) {
  if (/instagram\.com/.test(url)) return 'instagram';
  if (/youtu(be\.com|\.be)/.test(url)) return 'youtube';
  if (/facebook\.com|fb\.watch/.test(url)) return 'facebook';
  if (/tiktok\.com/.test(url)) return 'tiktok';
  return 'unknown';
}

function getCookiesPath(platform) {
  return path.join(__dirname, 'cookies.txt');
}

app.post('/get-info', async (req, res) => {
  console.log('DEBUG POST /get-info', req.body, new Date().toISOString());
  try {
    const { url } = req.body;
    console.log('DEBUG yt_dlp call', url);
    const platform = detectPlatform(url);
    const cookiesPath = getCookiesPath(platform);
    const cookieArg = fs.existsSync(cookiesPath) ? ` --cookies "${cookiesPath}"` : '';
    const { stdout } = await execAsync(`python -m yt_dlp -J "${url}" --no-warnings${cookieArg}`);
    const info = JSON.parse(stdout);
    let formats = [];
    if (platform === 'youtube') {
      formats = info.formats
        .filter(f => f.ext === 'mp4' && f.height)
        .map(f => ({
          height: f.height,
          quality: f.height + 'p',
          filesize: f.filesize || 0,
          title: info.title
        }))
        .sort((a, b) => b.height - a.height)
        .filter((f, i, arr) => arr.findIndex(x => x.height === f.height) === i)
        .slice(0, 6);
    }
    if (platform === 'facebook' || platform === 'tiktok') {
      formats = (info.formats || [])
        .filter(f => f.ext === 'mp4' && f.height)
        .map(f => ({
          height: f.height,
          quality: f.height + 'p',
          filesize: f.filesize || 0,
          title: info.title
        }))
        .sort((a, b) => b.height - a.height)
        .filter((f, i, arr) => arr.findIndex(x => x.height === f.height) === i)
        .slice(0, 4);
    }
    console.log('DEBUG response', { title: info.title, platform, formatsLength: formats.length });
    res.json({
      title: info.title,
      thumbnail: info.thumbnail,
      formats,
      duration: info.duration,
      platform
    });
  } catch (error) {
    console.log('DEBUG ERROR /get-info', error.message, error);
    res.status(500).json({ error: error.message });
  }
});

app.post('/yt-dlp-download', async (req, res) => {
  console.log('DEBUG POST /yt-dlp-download', req.body, new Date().toISOString());
  const { url, height, title, asMp3 } = req.body;
  const safeTitle = title.replace(/[^a-z0-9]/gi, '_');
  const dir = path.join(__dirname, 'downloads');
  if (!fs.existsSync(dir)) fs.mkdirSync(dir);
  const platform = detectPlatform(url);
  const cookiesPath = getCookiesPath(platform);
  const cookieParam = fs.existsSync(cookiesPath) ? ['--cookies', cookiesPath] : [];

  let outPattern = path.join(dir, `${safeTitle}.mp4`);
  let ytdlpArgs = [
    '-m', 'yt_dlp',
    url,
    '-o', outPattern,
    '--merge-output-format', 'mp4',
    '--no-playlist',
    '--no-warnings',
    ...cookieParam
  ];

  if (platform === 'youtube' && asMp3) {
    outPattern = path.join(dir, `${safeTitle}.mp3`);
    ytdlpArgs = [
      '-m', 'yt_dlp',
      '-x', '--audio-format', 'mp3',
      url,
      '-o', outPattern,
      '--no-playlist',
      '--no-warnings',
      ...cookieParam
    ];
  } else if ((platform === 'youtube' || platform === 'facebook' || platform === 'tiktok') && height) {
    ytdlpArgs.splice(3, 0, '-f', `bestvideo[ext=mp4][height=${height}]+bestaudio/best[ext=m4a]/best`);
  }

  console.log('DEBUG yt-dlp args', ytdlpArgs);

  const ytdlp = spawn('python', ytdlpArgs);

  ytdlp.on('close', (code) => {
    console.log('DEBUG yt-dlp close', code);
    if (code === 0) {
      res.json({ filename: path.basename(outPattern) });
    } else {
      res.status(500).json({ error: 'Download failed' });
    }
  });
});

app.get('/file/:filename', (req, res) => {
  console.log('DEBUG GET /file', req.params.filename, new Date().toISOString());
  const filepath = path.join(__dirname, 'downloads', req.params.filename);
  if (fs.existsSync(filepath)) {
    res.download(filepath, (err) => {
      if (!err) {
        setTimeout(() => {
          if (fs.existsSync(filepath)) {
            fs.unlinkSync(filepath);
            console.log('DEBUG unlink file', filepath);
          }
        }, 5000);
      }
    });
  } else {
    res.status(404).json({ error: 'File not found' });
  }
});

app.listen(PORT, '0.0.0.0', () => {
  console.log(`Server running on port ${PORT}`);
});
