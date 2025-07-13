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

app.post('/get-info', async (req, res) => {
  try {
    const { url } = req.body;
    const { stdout } = await execAsync(`python -m yt_dlp -J "${url}" --no-warnings`);
    const info = JSON.parse(stdout);
    const platform = detectPlatform(url);
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
    res.json({
      title: info.title,
      thumbnail: info.thumbnail,
      formats,
      duration: info.duration,
      platform
    });
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

app.post('/yt-dlp-download', async (req, res) => {
  const { url, height, title, asMp3 } = req.body;
  const safeTitle = title.replace(/[^a-z0-9]/gi, '_');
  const dir = path.join(__dirname, 'downloads');
  if (!fs.existsSync(dir)) fs.mkdirSync(dir);
  const platform = detectPlatform(url);

  let outPattern = path.join(dir, `${safeTitle}${asMp3 ? '.mp3' : '.mp4'}`);
  let ytdlpArgs;

  if (platform === 'youtube' && asMp3) {
    ytdlpArgs = [
      '-m', 'yt_dlp',
      '-x', '--audio-format', 'mp3',
      url,
      '-o', outPattern,
      '--no-playlist',
      '--no-warnings'
    ];
  } else if ((platform === 'youtube' || platform === 'facebook' || platform === 'tiktok') && height) {
    ytdlpArgs = [
      '-m', 'yt_dlp',
      '-f', `bestvideo[ext=mp4][vcodec^=avc1][fps>=24][height=${height}]+bestaudio[ext=m4a]/best[ext=mp4]`,
      url,
      '-o', outPattern,
      '--merge-output-format', 'mp4',
      '--no-playlist',
      '--no-warnings'
    ];
  } else {
    ytdlpArgs = [
      '-m', 'yt_dlp',
      url,
      '-o', outPattern,
      '--merge-output-format', 'mp4',
      '--no-playlist',
      '--no-warnings'
    ];
  }

  const ytdlp = spawn('python', ytdlpArgs);

  ytdlp.on('close', (code) => {
    if (code === 0) {
      res.json({ filename: path.basename(outPattern) });
    } else {
      res.status(500).json({ error: 'Download failed' });
    }
  });
});

app.get('/file/:filename', (req, res) => {
  const filepath = path.join(__dirname, 'downloads', req.params.filename);
  if (fs.existsSync(filepath)) {
    res.download(filepath, (err) => {
      if (!err) {
        setTimeout(() => {
          if (fs.existsSync(filepath)) {
            fs.unlinkSync(filepath);
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
