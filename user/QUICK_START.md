# Quick Start Guide - Draft Save Feature

## 🚀 3-Step Setup

### Step 1: Run Database Setup
Open in browser:
```
http://localhost/FinalResearch%20-%20Copy/user/test_draft_feature.php
```
✅ This will create the database table and verify everything is working.

### Step 2: Test the Feature
1. **Log in** as any user
2. **Click "Apply Now"** on any job listing
3. **Go to Step 2** (Submit Requirements)
4. **Upload your documents** (Letter, Resume, TOR, Diploma, etc.)
5. **Click the "Save Draft" button** (new amber/yellow button)
6. **See success message**: "Draft saved!"

### Step 3: See It in Action
1. **Go back** to dashboard
2. **Apply to a different job**
3. **Go to Step 2** again
4. **Magic!** 🎉 See blue notification: "Previously saved documents loaded!"
5. **Submit without uploading** - draft files are used automatically

---

## 🎯 What You'll See

### New Save Draft Button
Located in Step 2, between Back and Submit buttons:
```
[← Back]     [💾 Save Draft]  [📤 Submit Application]
           (amber/yellow)        (blue gradient)
```

### Notifications

**When saving draft:**
```
✅ Draft saved! Your documents will auto-load for future applications.
(Green notification, top-right, auto-dismisses after 5 seconds)
```

**When draft loads:**
```
📁 Previously saved documents loaded! You can reuse them or upload new ones.
(Blue notification, top-right, auto-dismisses after 5 seconds)
```

**Visual indicators on file inputs:**
```
📁 Saved draft available
(Blue text above file input, blue background on container)
```

---

## ✨ How It Works

1. **First Application**: Upload docs → Click "Save Draft" → Documents stored
2. **Next Application**: Open Step 2 → Docs auto-load → Submit directly or upload new ones
3. **Submit**: System uses draft files if no new files uploaded

---

## 🎓 Tips

- **Save early**: Create your draft on the first application
- **Update anytime**: Save draft again to update with new files
- **Mix files**: Use some draft files, upload new ones for others
- **Optional**: Can still upload everything manually each time

---

## 🔍 Troubleshooting

**Draft not loading?**
- Make sure you saved a draft before (click Save Draft button)
- Check browser console for errors (F12)

**Can't save draft?**
- Upload at least one file first
- Check file size (max 5MB per file)

**Files not found?**
- Run test script: `http://localhost/.../user/test_draft_feature.php`

---

## 📞 Need Help?

See full documentation: `DRAFT_SAVE_FEATURE_README.md`
