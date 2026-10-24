from pathlib import Path
import shutil, subprocess, sys, zipfile
root=Path(__file__).resolve().parent.parent
out=root/'artifacts'
out.mkdir(exist_ok=True)
files=[
 'desk1/private/bootstrap.php','desk1/private/update-lib.php','desk1/private/tools-layout.php',
 'desk1/desk.mortonsmechanical.com/index.php','desk1/desk.mortonsmechanical.com/google.php',
 'desk1/desk.mortonsmechanical.com/updates.php',
 'desk1/desk.mortonsmechanical.com/assets/service-tools.css',
 'desk1/desk.mortonsmechanical.com/assets/update-upload.js',
 'INSTALL_UPDATER.md']
with zipfile.ZipFile(out/'Service-Desk-Updater-Install.zip','w',zipfile.ZIP_DEFLATED) as z:
 for f in files:z.write(root/f,f)
payload=root/'package-payload'
if payload.exists():shutil.rmtree(payload)
(payload/'public/assets').mkdir(parents=True)
for name in ['index.php','google.php']:
 shutil.copy(root/'desk1/desk.mortonsmechanical.com'/name,payload/'public'/name)
shutil.copy(root/'desk1/desk.mortonsmechanical.com/assets/service-tools.css',payload/'public/assets/service-tools.css')
subprocess.run([sys.executable,str(root/'tools/build-update.py'),str(payload),str(out/'Service-Desk-Styling-Update.zip'),'--version','1.1.0','--title','Service Desk styling update'],check=True)
shutil.copy(root/'INSTALL_UPDATER.md',out/'INSTALL_UPDATER.md')
