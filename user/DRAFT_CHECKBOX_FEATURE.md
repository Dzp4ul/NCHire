# Draft Checkbox Feature - Enhanced UI

## ✨ New Features Added

### 1. **Show Saved Filenames**
When drafts are loaded, you now see the actual filenames instead of just a generic message.

### 2. **Checkbox Control**
Each saved draft has a checkbox so you can choose which drafts to use.

### 3. **Remove Button (X)**
Each draft has an X button to remove it and upload a new file instead.

---

## 🎨 Visual Changes

### Before (Original):
```
┌─────────────────────────────────────┐
│ 📁 Saved draft available            │
│                                     │
│ [Choose File] No file chosen        │
└─────────────────────────────────────┘
```

### After (Enhanced):
```
┌──────────────────────────────────────────────┐
│ ☑️ Use Saved Draft              ❌          │
│ 📄 my_application_letter.pdf                │
│                                              │
│ ℹ️ Check to use this saved file, or         │
│    uncheck and upload a new one              │
└──────────────────────────────────────────────┘
```

---

## 📝 How It Works

### When Draft Loads:

1. **Checkbox** (☑️): Checked by default
   - ✅ **Checked** = Use this saved draft file
   - ☐ **Unchecked** = Don't use draft, will require new upload

2. **Filename Display**: Shows actual file name
   - Example: `my_resume.pdf`
   - Cleans up internal prefixes (removes `draft_123_1234567890_`)

3. **Remove Button** (❌): Click to:
   - Remove the draft display
   - Show the file upload input again
   - Make file upload required

4. **Help Text**: Explains checkbox usage

---

## 🎯 User Scenarios

### Scenario 1: Use All Saved Drafts
1. Open Step 2 - drafts load automatically
2. All checkboxes are checked ✅
3. Click "Submit Application"
4. **Result**: All draft files are used

### Scenario 2: Use Some Drafts, Upload Some New
1. Open Step 2 - drafts load automatically
2. **Uncheck** the resume checkbox ☐
3. File input appears for resume
4. Upload new resume file
5. Keep other checkboxes checked ✅
6. Click "Submit Application"
7. **Result**: New resume + other draft files

### Scenario 3: Remove a Draft Completely
1. Open Step 2 - drafts load automatically
2. Click **X button** on Application Letter
3. Draft display removed
4. File input appears (required)
5. Upload new file
6. Click "Submit Application"
7. **Result**: New letter + other draft files

### Scenario 4: Don't Use Any Drafts
1. Open Step 2 - drafts load automatically
2. **Uncheck all** checkboxes ☐
3. File inputs appear for all
4. Upload all new files
5. Click "Submit Application"
6. **Result**: All new files, drafts ignored

---

## 🔧 Technical Details

### Checkbox Behavior

**HTML Structure:**
```html
<input type="checkbox" 
       id="useDraft_application_letter" 
       checked
       data-field="application_letter"
       data-input-name="applicationLetter">
```

**When Checked (✅):**
- Draft file will be used if no new file uploaded
- File input stays hidden
- Not required

**When Unchecked (☐):**
- Draft file ignored
- System expects new file upload
- File input becomes required

### File Display Format

**Saved Filename in Database:**
```
draft_123_1234567890_my_resume.pdf
```

**Displayed to User:**
```
📄 my_resume.pdf
```

The internal prefix is removed for cleaner display.

### Form Submission

When submitting, JavaScript collects all checkbox states:

```javascript
{
  "application_letter": true,  // ✅ Use draft
  "resume": false,             // ☐ Don't use draft
  "tor": true,                 // ✅ Use draft
  "diploma": true,             // ✅ Use draft
  ...
}
```

PHP backend checks these flags:
- If checkbox checked + no new upload → Use draft file
- If checkbox unchecked → Require new upload
- If new file uploaded → Use new file (ignore draft)

---

## 🎨 Visual Elements

### Blue Draft Box
```
┌────────────────────────────────────┐
│  Background: Light blue (#bfdbfe)  │
│  Border: Blue (#3b82f6)            │
│  Rounded corners                   │
└────────────────────────────────────┘
```

### Checkbox
- Size: 16x16px (w-4 h-4)
- Color: Blue when checked
- Focus ring on keyboard navigation

### Remove Button
- Icon: X circle (ri-close-line)
- Color: Red on hover
- Background: Light red on hover
- Tooltip: "Remove draft and upload new file"

### File Icon
- Icon: Document with fill (ri-file-text-fill)
- Color: Blue (#2563eb)
- Size: Large (text-lg)

---

## 📊 Benefits

### User Benefits:
- ✅ **See what files** are saved (actual filenames)
- ✅ **Choose which drafts** to use (checkbox control)
- ✅ **Easy removal** of unwanted drafts (X button)
- ✅ **Mix and match** drafts + new uploads
- ✅ **Clear feedback** on what will be submitted

### System Benefits:
- ✅ **User control**: Users decide which drafts to use
- ✅ **Flexibility**: Can use some drafts, upload new for others
- ✅ **Clear UI**: Shows exactly what files will be used
- ✅ **Better UX**: No confusion about what's being submitted

---

## 🧪 Testing

### Test Checklist:
- [ ] Draft loads and shows filename
- [ ] Checkbox is checked by default
- [ ] Unchecking checkbox shows file input
- [ ] Checking checkbox hides file input
- [ ] X button removes draft display
- [ ] X button shows file input (required)
- [ ] Submit with all checkboxes checked uses all drafts
- [ ] Submit with some unchecked requires new uploads
- [ ] New file upload overrides draft even if checked
- [ ] Multiple applications reuse same drafts

---

## 💡 Tips

### For Users:
1. **Default behavior**: All drafts selected - just click Submit
2. **Replace one file**: Uncheck that checkbox, upload new one
3. **Remove draft**: Click X button if you don't want that draft anymore
4. **See filename**: Hover over the blue box to see full filename

### For Developers:
1. **Checkbox state**: Sent as JSON in `use_drafts` POST parameter
2. **File priority**: New upload > Checked draft > Error
3. **Multiple files**: Seminars/certificates can have multiple filenames
4. **Clean names**: Display names strip internal prefixes

---

## 🎉 Summary

The enhanced draft feature now gives users complete control:

1. ☑️ **Checkboxes** - Choose which drafts to use
2. 📄 **Filenames** - See actual file names
3. ❌ **Remove buttons** - Delete unwanted drafts
4. 📝 **Instructions** - Clear help text

This makes the draft system more transparent and user-friendly!
