<template>
  <div class="container video-merge-container">
    <h2>Fusion</h2>
    <div class="card">
      <h3 class="card-header">Merge Videos</h3>

      <div class="mb-3 upload-section">
        <label for="videos">Upload Videos</label>
        <input
          type="file"
          id="videos"
          multiple
          @change="handleVideoUpload"
          accept="video/mp4"
          class="form-control"
        />
      </div>

      <div class="mb-3 upload-section">
        <label for="intro">Introduction Text</label>
        <input
        class="form-control"
          type="text"
          id="intro"
          v-model="intro"
          placeholder="Enter introduction text"
        />
      </div>

      <div v-if="videos.length > 0" class="video-preview">
        <h4>Preview Videos (Drag to reorder):</h4>
        <draggable v-model="videos" tag="ul" handle=".video-handle">
          <template #item="{ element, index }">
            <li :key="index" class="d-flex video-item">
              <div class="d-flex align-items-center mb-2">
                <div class="video-handle me-3">☰</div>
                <video :src="element.url" controls width="400" class="me-3" />
              </div>
              <div class="d-flex flex-column gap-2">
                <p>{{ element.name }}</p>
                <button
                  @click="removeVideo(index)"
                  class="btn btn-sm btn-danger"
                >
                  Remove
                </button>
              </div>
            </li>
          </template>
        </draggable>
      </div>

      <!-- <div class="mb-3">
        <label for="position">Where should the image appear?</label>
        <select v-model="position" id="position" class="form-select">
          <option value="start">Start</option>
          <option value="end">End</option>
        </select>
      </div> -->

      <div class="actions d-flex justify-content-between">
        <button
          class="btn btn-primary"
          type="submit"
          :disabled="loading"
          @click="mergeVideos"
        >
          Merge and Download
        </button>
        <div v-if="loading" class="loading-spinner">
          <div class="spinner"></div>
        </div>
        <div v-if="downloadUrl" @click="downloadMergedVideo" class="btn btn-success">Download</div>
      </div>
    </div>
  </div>
</template>
<script>
import { ref } from "vue";
import axios from "axios";
import draggable from "vuedraggable";

export default {
  name: "video-merge",
  components: {
    draggable,
  },
  setup() {
    const videos = ref([]);
    const intro = ref("");
    const position = ref("start");
    const loading = ref(false);
    const downloadUrl = ref(null);

    const handleVideoUpload = (event) => {
      const videoFiles = event.target.files;
      Array.from(videoFiles).forEach((file) => {
        videos.value.push({
          file,
          name: file.name,
          url: URL.createObjectURL(file),
        });
      });
    };

    const handleImageUpload = (event) => {
      intro.value = event.target.files[0];
    };

    const removeVideo = (index) => {
      videos.value.splice(index, 1);
    };

    const mergeVideos = async () => {
      if (videos.value.length < 2 || intro.value == "") {
        alert("Please upload videos and introduction text.");
        return;
      }

      loading.value = true;

      const formData = new FormData();
      videos.value.forEach((video) => {
        formData.append("videos[]", video.file);
      });
      formData.append("intro", intro.value);
      formData.append("position", position.value);

      try {
        const response = await axios.post(
          "http://localhost:8000/api/merge-videos",
          formData,
          {
            responseType: "json",
          }
        );

        if (response.data.success) {
          console.log(response.data);
          console.log(response.data.success);
          const url = response.data.data.download_url;
          downloadUrl.value = `http://localhost:8000/storage/temporary/${url}`; // Add the correct domain
        } else {
          alert("Video merging failed.");
        }
      } catch (error) {
        console.error("Error:", error.response?.data || error.message);
        alert("An error occurred while merging the videos.");
      } finally {
        loading.value = false;
      }
    };

    const downloadMergedVideo = async () => {
      if (!downloadUrl.value) {
        alert("Download URL not available.");
        return;
      }

      // Create a temporary <a> element to initiate download
      const link = document.createElement("a");
      link.href = downloadUrl.value;
      link.download = "output.mp4"; // Set the filename to be downloaded
      link.click(); // Trigger the download automatically
    };

    return {
      videos,
      intro,
      position,
      loading,
      downloadUrl,
      handleVideoUpload,
      handleImageUpload,
      removeVideo,
      mergeVideos,
      downloadMergedVideo,
    };
  },
};
</script>

<style scoped>
.video-merge-container {
  max-width: 700px;
  margin: 20px auto;
  padding: 20px;
  font-family: Arial, sans-serif;
}

.card {
  background: #ffffff;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.title {
  text-align: center;
  margin-bottom: 20px;
}

.upload-section {
  margin-bottom: 15px;
}

.upload-section label {
  font-weight: bold;
  display: block;
  margin-bottom: 5px;
}

.video-preview {
  margin-bottom: 20px;
}

.video-item {
  display: flex;
  align-items: center;
  margin-bottom: 10px;
  border: 1px solid #ddd;
  padding: 10px;
  border-radius: 8px;
}

.video-handle {
  cursor: pointer;
  margin-right: 10px;
  font-size: 18px;
}

video {
  margin-right: 10px;
}

button {
  background-color: #007bff;
  color: white;
  padding: 10px 20px;
  border: none;
  border-radius: 5px;
  cursor: pointer;
}

button:disabled {
  background-color: #c5c5c5;
}

.actions {
  text-align: center;
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 5px;
  cursor: pointer;
}

.primary {
  background-color: #007bff;
  color: white;
}

.download {
  background-color: #28a745;
  color: white;
}

.loading-spinner {
  text-align: center;
  margin-top: 20px;
}

.spinner {
  border: 4px solid rgba(255, 255, 255, 0.3);
  border-top: 4px solid #007bff;
  border-radius: 50%;
  width: 50px;
  height: 50px;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}
</style>
